<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chamber;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StockLot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Decision-ready aggregates (PRD 3.11). Every query runs inside the tenant
 * scope; branch filtering piggybacks on the caller's X-Branch-Id where the
 * underlying tables are branch-scoped. Money is poisha throughout — the SPA
 * formats.
 *
 * Date-range endpoints accept ?from=YYYY-MM-DD&to=YYYY-MM-DD (default: last
 * 30 days). DATE() is used for series buckets — portable across MySQL/SQLite.
 */
final class ReportController extends Controller
{
    /**
     * Per-chamber fill: lots + weight versus capacity.
     */
    public function occupancy(): JsonResponse
    {
        $chambers = Chamber::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'chamber_type', 'status', 'capacity_weight_kg']);

        $usage = StockLot::query()
            ->whereIn('status', ['in_storage', 'partially_released'])
            ->whereNotNull('chamber_id')
            ->selectRaw('chamber_id, COUNT(*) as lots, SUM(quantity) as quantity, SUM(weight_kg) as weight_kg')
            ->groupBy('chamber_id')
            ->get()
            ->keyBy('chamber_id');

        $rows = $chambers->map(function (Chamber $c) use ($usage): array {
            $u        = $usage->get($c->id);
            $weight   = (float) ($u->weight_kg ?? 0);
            $capacity = $c->capacity_weight_kg !== null ? (float) $c->capacity_weight_kg : null;

            return [
                'chamber_id'         => $c->id,
                'name'               => $c->name,
                'code'               => $c->code,
                'chamber_type'       => $c->chamber_type,
                'status'             => $c->status,
                'lots'               => (int) ($u->lots ?? 0),
                'quantity'           => (float) ($u->quantity ?? 0),
                'weight_kg'          => $weight,
                'capacity_weight_kg' => $capacity,
                'occupancy_pct'      => ($capacity !== null && $capacity > 0)
                    ? round($weight / $capacity * 100, 1)
                    : null,
            ];
        });

        return response()->json(['data' => $rows]);
    }

    /**
     * Billed vs collected: totals + a daily series for the range.
     */
    public function revenue(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $billed = Invoice::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$from, $to])
            ->where('status', '!=', 'void')
            ->selectRaw('DATE(issued_at) as day, SUM(total_poisha) as amount')
            ->groupBy('day')->orderBy('day')
            ->pluck('amount', 'day');

        $collected = Payment::query()
            ->where('status', 'completed')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as day, SUM(amount_poisha) as amount')
            ->groupBy('day')->orderBy('day')
            ->pluck('amount', 'day');

        $series = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key      = $d->toDateString();
            $series[] = [
                'date'             => $key,
                'billed_poisha'    => (int) ($billed[$key] ?? 0),
                'collected_poisha' => (int) ($collected[$key] ?? 0),
            ];
        }

        return response()->json(['data' => [
            'from'                   => $from->toDateString(),
            'to'                     => $to->toDateString(),
            'total_billed_poisha'    => (int) $billed->sum(),
            'total_collected_poisha' => (int) $collected->sum(),
            'series'                 => $series,
        ]]);
    }

    /**
     * Outstanding dues per customer with AR aging buckets (0–30/31–60/61–90/90+).
     */
    public function receivables(): JsonResponse
    {
        $open = Invoice::query()
            ->with('customer:id,code,name,phone')
            ->where('amount_due_poisha', '>', 0)
            ->whereNotNull('issued_at')
            ->where('status', '!=', 'void')
            ->get(['id', 'customer_id', 'invoice_number', 'amount_due_poisha', 'issued_at']);

        $now       = now();
        $buckets   = ['0_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
        $customers = [];

        foreach ($open as $invoice) {
            $age    = (int) $invoice->issued_at->diffInDays($now);
            $bucket = match (true) {
                $age <= 30 => '0_30',
                $age <= 60 => '31_60',
                $age <= 90 => '61_90',
                default    => '90_plus',
            };
            $due = (int) $invoice->amount_due_poisha;

            $buckets[$bucket] += $due;

            $cid = $invoice->customer_id;
            $customers[$cid] ??= [
                'customer_id' => $cid,
                'code'        => $invoice->customer?->code,
                'name'        => $invoice->customer?->name,
                'phone'       => $invoice->customer?->phone,
                'due_poisha'  => 0,
                'invoices'    => 0,
                'oldest_days' => 0,
            ];
            $customers[$cid]['due_poisha'] += $due;
            $customers[$cid]['invoices']++;
            $customers[$cid]['oldest_days'] = max($customers[$cid]['oldest_days'], $age);
        }

        usort($customers, static fn (array $a, array $b): int => $b['due_poisha'] <=> $a['due_poisha']);

        return response()->json(['data' => [
            'total_due_poisha' => array_sum($buckets),
            'aging'            => $buckets,
            'customers'        => array_values($customers),
        ]]);
    }

    /**
     * What is in storage right now, by product — plus lots expiring soon.
     */
    public function stock(Request $request): JsonResponse
    {
        $inStorage = StockLot::query()
            ->whereIn('status', ['in_storage', 'partially_released'])
            ->with('product:id,code,name,unit_of_measure')
            ->get(['id', 'product_id', 'quantity', 'weight_kg', 'unit_of_measure', 'expiry_date', 'lot_code', 'customer_id']);

        $byProduct = $inStorage
            ->groupBy(fn (StockLot $lot) => $lot->product_id ?? 0)
            ->map(function ($lots, $productId): array {
                $first = $lots->first();

                return [
                    'product_id' => $productId === 0 ? null : $productId,
                    'code'       => $first->product?->code,
                    'name'       => $first->product?->name ?? 'Uncategorised',
                    'unit'       => $first->product?->unit_of_measure ?? $first->unit_of_measure,
                    'lots'       => $lots->count(),
                    'quantity'   => (float) $lots->sum('quantity'),
                    'weight_kg'  => (float) $lots->sum('weight_kg'),
                ];
            })
            ->sortByDesc('quantity')
            ->values();

        $horizon  = (int) $request->integer('expiring_days', 30);
        $expiring = $inStorage
            ->filter(fn (StockLot $lot): bool => $lot->expiry_date !== null
                && $lot->expiry_date->lte(now()->addDays($horizon)))
            ->sortBy('expiry_date')
            ->take(50)
            ->map(fn (StockLot $lot): array => [
                'lot_id'      => $lot->id,
                'lot_code'    => $lot->lot_code,
                'product'     => $lot->product?->name,
                'quantity'    => (float) $lot->quantity,
                'unit'        => $lot->unit_of_measure,
                'expiry_date' => $lot->expiry_date->toDateString(),
                'days_left'   => (int) now()->startOfDay()->diffInDays($lot->expiry_date, false),
            ])
            ->values();

        return response()->json(['data' => [
            'total_lots'     => $inStorage->count(),
            'by_product'     => $byProduct,
            'expiring_soon'  => $expiring,
            'expiring_days'  => $horizon,
        ]]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        $to   = $request->filled('to') ? Carbon::parse((string) $request->string('to'))->endOfDay() : now()->endOfDay();
        $from = $request->filled('from') ? Carbon::parse((string) $request->string('from'))->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        return [$from, $to];
    }
}
