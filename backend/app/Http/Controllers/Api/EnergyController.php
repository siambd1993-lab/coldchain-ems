<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
