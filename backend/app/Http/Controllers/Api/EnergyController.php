<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chamber;
use App\Models\Device;
use App\Models\EnergyConsumption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Energy analytics over the daily consumption rollups (PRD 04): totals and a
 * per-source daily series for the requested window. Live telemetry needs the
 * MQTT pipeline; this endpoint reads the persisted daily rows.
 */
final class EnergyController extends Controller
{
    /** BD evening peak tariff window (hours, facility-local time). */
    private const PEAK_START = 17;
    private const PEAK_END   = 23;

    /**
     * Instantaneous source→load power flows for the Tesla-style panel.
     *
     * Until the MQTT pipeline feeds real meter telemetry, values come from a
     * deterministic time-of-day model (solar bell curve, compressor cycling,
     * battery charging midday / discharging through the evening peak). The
     * payload carries mode=simulated so the UI can badge it; swapping in real
     * channel readings later keeps the exact same shape.
     */
    public function live(): JsonResponse
    {
        $now  = Carbon::now(config('app.display_timezone', 'Asia/Dhaka'));
        $hour = $now->hour + $now->minute / 60;

        // Solar: daylight bell between 06:00 and 18:00, ~15 kW peak.
        $solar = 0.0;
        if ($hour > 6 && $hour < 18) {
            $solar = round(15 * sin(M_PI * ($hour - 6) / 12), 1);
        }

        // Load: compressor base + duty cycling + warmer-afternoon extra.
        $cycle = sin($now->minute / 15 * M_PI) * 2.0;
        $load  = round(9.5 + max(0, $cycle) + ($hour > 11 && $hour < 17 ? 1.5 : 0), 1);

        // Battery state of charge follows the daily charge/discharge rhythm.
        $soc = match (true) {
            $hour < 6   => 45 - $hour,                       // slow overnight drain
            $hour < 16  => min(95, 40 + ($hour - 6) * 5.5),  // solar charging
            $hour < 23  => max(25, 95 - ($hour - 16) * 9),   // peak discharge
            default     => 35,
        };
        $soc = round($soc, 0);

        $isPeak    = $hour >= self::PEAK_START && $hour < self::PEAK_END;
        $generator = 0.0; // idle unless a grid outage is simulated upstream

        // Dispatch: solar covers load first; surplus charges the battery then
        // exports; deficits draw from the battery during peak, else the grid.
        $solarToLoad    = min($solar, $load);
        $surplus        = max(0.0, $solar - $load);
        $solarToBattery = $soc < 95 ? round(min($surplus, 5.0), 1) : 0.0;
        $solarExport    = round($surplus - $solarToBattery, 1);

        $deficit        = max(0.0, $load - $solar);
        $batteryToLoad  = ($isPeak && $soc > 25) ? round(min($deficit, 8.0), 1) : 0.0;
        $gridToLoad     = round($deficit - $batteryToLoad, 1);

        $flows = array_values(array_filter([
            ['from' => 'solar',     'to' => 'load',    'kw' => round($solarToLoad, 1)],
            ['from' => 'solar',     'to' => 'battery', 'kw' => $solarToBattery],
            ['from' => 'solar',     'to' => 'grid',    'kw' => $solarExport],
            ['from' => 'battery',   'to' => 'load',    'kw' => $batteryToLoad],
            ['from' => 'grid',      'to' => 'load',    'kw' => $gridToLoad],
            ['from' => 'generator', 'to' => 'load',    'kw' => $generator],
        ], fn (array $f): bool => $f['kw'] > 0.05));

        return response()->json(['data' => [
            'mode'         => 'simulated',
            'timestamp'    => $now->toIso8601String(),
            'is_peak_hour' => $isPeak,
            'solar_kw'     => $solar,
            'load_kw'      => $load,
            'generator_kw' => $generator,
            'grid_kw'      => round($gridToLoad - $solarExport, 1), // + import / − export
            'battery'      => [
                'soc_pct' => $soc,
                'kw'      => round($batteryToLoad - $solarToBattery, 1), // + discharge / − charge
            ],
            'flows'        => $flows,
        ]]);
    }

    /**
     * AI v1 (rule-based + statistics, per PRD 11.1): concrete recommendations
     * and anomaly flags derived from the recorded consumption, tariff windows,
     * chamber setpoints and device health.
     */
    public function insights(): JsonResponse
    {
        $insights = [];

        $rows = EnergyConsumption::query()
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->get(['date', 'source', 'energy_kwh', 'cost_poisha']);

        $daily = $rows->groupBy(fn ($r) => (string) $r->date)
            ->map(fn ($g) => (float) $g->sum('energy_kwh'));

        // ── Anomaly detection: day > mean + 2σ ────────────────────────────
        if ($daily->count() >= 7) {
            $mean = $daily->avg();
            $sd   = sqrt($daily->map(fn (float $v): float => ($v - $mean) ** 2)->avg());

            foreach ($daily->sortKeys()->slice(-7) as $date => $kwh) {
                if ($sd > 0 && $kwh > $mean + 2 * $sd) {
                    $insights[] = [
                        'type'     => 'anomaly',
                        'severity' => 'warning',
                        'title'    => "Unusual consumption on {$date}",
                        'detail'   => sprintf(
                            '%.0f kWh against a 30-day average of %.0f kWh (+%.0f%%). Check door seals, defrost cycles and compressor behaviour that day.',
                            $kwh, $mean, ($kwh / $mean - 1) * 100,
                        ),
                    ];
                }
            }
        }

        // ── Peak-tariff avoidance ─────────────────────────────────────────
        $gridKwh  = (float) $rows->where('source', 'grid')->sum('energy_kwh');
        $gridCost = (int) $rows->where('source', 'grid')->sum('cost_poisha');
        if ($gridKwh > 0) {
            $avgRate      = $gridCost / $gridKwh;                  // poisha per kWh
            $peakShareKwh = $gridKwh * 0.25;                       // evening block ≈ 6/24h weighted
            $premium      = $avgRate * 0.35;                       // BD ToD peak premium ≈ 35 %
            $saving       = (int) round($peakShareKwh * $premium);

            $insights[] = [
                'type'                  => 'peak_shift',
                'severity'              => 'opportunity',
                'title'                 => 'Shift evening-peak load to battery + pre-cooling',
                'detail'                => sprintf(
                    'Roughly %.0f kWh/month is drawn from the grid during the %02d:00–%02d:00 peak window. Discharging the battery through the peak and pre-cooling chambers before %02d:00 avoids the tariff premium.',
                    $peakShareKwh, self::PEAK_START, self::PEAK_END, self::PEAK_START,
                ),
                'saving_poisha_monthly' => $saving,
            ];
        }

        // ── Solar forecast (7-day moving average — statistics, not weather) ──
        $solarDaily = $rows->where('source', 'solar')
            ->groupBy(fn ($r) => (string) $r->date)
            ->map(fn ($g) => (float) $g->sum('energy_kwh'));
        if ($solarDaily->count() >= 7) {
            $avg = $solarDaily->sortKeys()->slice(-7)->avg();
            $insights[] = [
                'type'     => 'solar_forecast',
                'severity' => 'info',
                'title'    => sprintf('Solar outlook: ≈ %.0f kWh/day', $avg),
                'detail'   => sprintf(
                    'Based on the trailing 7-day average. Schedule battery charging and heavy pre-cooling for late morning to soak it up. Weather-model forecasting activates with the live pipeline.',
                ),
            ];
        }

        // ── Compressor scheduling vs chamber bands ────────────────────────
        $chambers = Chamber::query()->where('status', 'operational')
            ->whereNotNull('target_temp_min_c')
            ->get(['name', 'target_temp_min_c', 'target_temp_max_c']);
        if ($chambers->isNotEmpty()) {
            $names = $chambers->pluck('name')->take(3)->implode(', ');
            $insights[] = [
                'type'     => 'compressor_schedule',
                'severity' => 'opportunity',
                'title'    => 'Pre-cool chambers before the evening peak',
                'detail'   => sprintf(
                    'Drive %s toward the bottom of their temperature bands between 14:00–17:00 (cheap solar/off-peak power), then let them drift inside the band through the peak. Typical compressor energy shift: 15–25%% of evening load.',
                    $names,
                ),
            ];
        }

        // ── Predictive maintenance v1: device health signals ──────────────
        $staleDevices = Device::query()
            ->whereIn('status', ['online', 'provisioning'])
            ->where(function ($q): void {
                $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subHours(24));
            })->count();
        $faulted = Device::query()->whereIn('status', ['fault', 'offline'])->count();
        if ($staleDevices + $faulted > 0) {
            $insights[] = [
                'type'     => 'maintenance',
                'severity' => 'warning',
                'title'    => 'Devices need attention',
                'detail'   => sprintf(
                    '%d device(s) reporting fault/offline and %d silent for 24h+. Silent sensors hide temperature excursions — inspect power, wiring and network first.',
                    $faulted, $staleDevices,
                ),
            ];
        }

        // ── Generator (diesel) cost watch ─────────────────────────────────
        $genCost = (int) $rows->where('source', 'generator')->sum('cost_poisha');
        if ($genCost > 0) {
            $insights[] = [
                'type'     => 'generator',
                'severity' => 'info',
                'title'    => 'Generator ran this month',
                'detail'   => 'Diesel generation costs ~3× grid power. Battery capacity sized to ride through short outages cuts most of this cost.',
                'cost_poisha_monthly' => $genCost,
            ];
        }

        return response()->json(['data' => [
            'generated_at' => now()->toIso8601String(),
            'engine'       => 'rules-v1',
            'insights'     => $insights,
        ]]);
    }

    public function summary(Request $request): JsonResponse
    {
        $to   = $request->filled('to') ? Carbon::parse((string) $request->string('to')) : now();
        $from = $request->filled('from') ? Carbon::parse((string) $request->string('from')) : $to->copy()->subDays(29);

        $rows = EnergyConsumption::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('date, source, SUM(energy_kwh) as kwh, SUM(cost_poisha) as cost_poisha, SUM(co2_kg) as co2_kg')
            ->groupBy('date', 'source')
            ->orderBy('date')
            ->get();

        $bySource = $rows->groupBy('source')->map(fn ($group, string $source): array => [
            'source'      => $source,
            'kwh'         => round((float) $group->sum('kwh'), 2),
            'cost_poisha' => (int) $group->sum('cost_poisha'),
            'co2_kg'      => round((float) $group->sum('co2_kg'), 1),
        ])->values();

        $series = $rows->groupBy(fn ($r) => (string) $r->date)
            ->map(function ($group, string $date): array {
                $entry = ['date' => $date, 'grid' => 0.0, 'solar' => 0.0, 'generator' => 0.0, 'mixed' => 0.0];
                foreach ($group as $r) {
                    $entry[$r->source] = round((float) $r->kwh, 2);
                }

                return $entry;
            })
            ->values();

        $totalKwh  = (float) $rows->sum('kwh');
        $solarKwh  = (float) $rows->where('source', 'solar')->sum('kwh');

        return response()->json(['data' => [
            'from'             => $from->toDateString(),
            'to'               => $to->toDateString(),
            'total_kwh'        => round($totalKwh, 2),
            'total_cost_poisha' => (int) $rows->sum('cost_poisha'),
            'total_co2_kg'     => round((float) $rows->sum('co2_kg'), 1),
            'solar_share_pct'  => $totalKwh > 0 ? round($solarKwh / $totalKwh * 100, 1) : 0.0,
            'by_source'        => $bySource,
            'series'           => $series,
        ]]);
    }
}
