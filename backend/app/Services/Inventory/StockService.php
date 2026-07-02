<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\StorageUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Append-only stock ledger: every quantity change is a new {@see StockMovement}
 * row; the lot's `quantity` and `weight_kg` are the running totals derived from
 * those rows (maintained here for fast reads).
 *
 * All mutating methods run inside a transaction and re-fetch the lot with
 * `lockForUpdate` so concurrent requests cannot double-release or over-adjust.
 *
 * Money lives in the billing layer. This service only tracks physical goods.
 */
final class StockService
{
    /**
     * Receive a new lot into storage. Creates the lot AND its first stock_in
     * movement atomically.
     *
     * @param  array<string, mixed>  $attributes  validated payload (see StoreStockLotRequest)
     * @return array{lot: StockLot, movement: StockMovement}
     */
    public function intake(array $attributes, ?int $performedBy): array
    {
        return DB::transaction(function () use ($attributes, $performedBy): array {
            $quantity  = (float) $attributes['quantity'];
            $weightKg  = isset($attributes['weight_kg']) ? (float) $attributes['weight_kg'] : null;
            $occurredAt = $attributes['received_at'] ?? now();

            $branchId = (int) $attributes['branch_id'];

            $lot = StockLot::create([
                'branch_id'          => $branchId,
                'lot_code'           => $attributes['lot_code'] ?? $this->generateLotCode($branchId),
                'customer_id'        => $attributes['customer_id'],
                'product_id'         => $attributes['product_id'] ?? null,
                'chamber_id'         => $attributes['chamber_id'] ?? null,
                'storage_unit_id'    => $attributes['storage_unit_id'] ?? null,
                'rate_plan_id'       => $attributes['rate_plan_id'] ?? null,
                'description'        => $attributes['description'] ?? null,
                'status'             => 'in_storage',
                'unit_of_measure'    => $attributes['unit_of_measure'] ?? 'kg',
                'initial_quantity'   => $quantity,
                'quantity'           => $quantity,
                'initial_weight_kg'  => $weightKg,
                'weight_kg'          => $weightKg,
                'package_count'      => $attributes['package_count'] ?? null,
                'grade'              => $attributes['grade'] ?? null,
                'marks'              => $attributes['marks'] ?? null,
                'received_at'        => $occurredAt,
                'expected_release_at' => $attributes['expected_release_at'] ?? null,
                'expiry_date'        => $attributes['expiry_date'] ?? null,
                'metadata'           => $attributes['metadata'] ?? null,
            ]);

            $movement = StockMovement::create([
                'lot_id'               => $lot->getKey(),
                'branch_id'            => $lot->branch_id,
                'type'                 => StockMovementType::StockIn->value,
                'quantity'             => $quantity,
                'weight_kg'            => $weightKg,
                'package_count'        => $attributes['package_count'] ?? null,
                'balance_after'        => $quantity,
                'to_chamber_id'        => $attributes['chamber_id'] ?? null,
                'to_storage_unit_id'   => $attributes['storage_unit_id'] ?? null,
                'reference'            => $attributes['reference'] ?? null,
                'performed_by'         => $performedBy,
                'occurred_at'          => $occurredAt,
            ]);

            // Stamp the storage unit's occupancy (best-effort; weight is optional).
            if (isset($attributes['storage_unit_id']) && $weightKg !== null) {
                StorageUnit::withoutBranchScope()
                    ->whereKey($attributes['storage_unit_id'])
                    ->increment('occupied_weight_kg', $weightKg);
            }

            return ['lot' => $lot, 'movement' => $movement];
        });
    }

    /**
     * Next sequential lot code for a branch (LOT-<year>-<seq>). Mirrors the
     * invoice/payment number generators; must be called inside an open write
     * transaction so concurrent intakes cannot mint the same sequence.
     */
    private function generateLotCode(int $branchId): string
    {
        $year = now()->year;

        $last = StockLot::withoutBranchScope()
            ->withTrashed()
            ->where('branch_id', $branchId)
            ->where('lot_code', 'like', "LOT-{$year}-%")
            ->lockForUpdate()
            ->count();

        return sprintf('LOT-%d-%05d', $year, $last + 1);
    }

    /**
     * Release some or all of a lot (stock_out). Decrements the lot's quantity and
     * weight, transitions its status, and updates storage-unit occupancy.
     *
     * @param  array<string, mixed>  $attributes
     * @throws ValidationException when the requested quantity exceeds available stock
     */
    public function release(StockLot $lot, array $attributes, ?int $performedBy): StockMovement
    {
        return DB::transaction(function () use ($lot, $attributes, $performedBy): StockMovement {
            // Re-fetch with a write lock so no concurrent release can race us.
            $lot = StockLot::whereKey($lot->getKey())->lockForUpdate()->firstOrFail();

            $releaseQty = (float) $attributes['quantity'];
            $weightKg   = isset($attributes['weight_kg']) ? (float) $attributes['weight_kg'] : null;

            if ($releaseQty <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Release quantity must be greater than zero.',
                ]);
            }

            if ($releaseQty > (float) $lot->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'Release quantity (%.3f) exceeds available stock (%.3f).',
                        $releaseQty,
                        (float) $lot->quantity,
                    ),
                ]);
            }

            $originalUnitId = $lot->storage_unit_id;
            $newBalance = (float) $lot->quantity - $releaseQty;

            $movement = StockMovement::create([
                'lot_id'               => $lot->getKey(),
                'branch_id'            => $lot->branch_id,
                'type'                 => StockMovementType::StockOut->value,
                'quantity'             => $releaseQty,
                'weight_kg'            => $weightKg,
                'package_count'        => $attributes['package_count'] ?? null,
                'balance_after'        => $newBalance,
                'from_chamber_id'      => $lot->chamber_id,
                'from_storage_unit_id' => $originalUnitId,
                'reference'            => $attributes['reference'] ?? null,
                'reason'               => $attributes['reason'] ?? null,
                'performed_by'         => $performedBy,
                'occurred_at'          => $attributes['occurred_at'] ?? now(),
                'metadata'             => $attributes['metadata'] ?? null,
            ]);

            $lot->quantity = $newBalance;

            if ($newBalance <= 0.0) {
                $lot->status       = 'released';
                $lot->released_at  = now();
                $lot->storage_unit_id = null;
            } else {
                $lot->status = 'partially_released';
            }

            if ($weightKg !== null && $lot->weight_kg !== null) {
                $lot->weight_kg = max(0.0, (float) $lot->weight_kg - $weightKg);
            }

            $lot->save();

            // Decrement occupancy using the unit captured before we nulled it.
            if ($originalUnitId !== null && $weightKg !== null) {
                StorageUnit::withoutBranchScope()
                    ->whereKey($originalUnitId)
                    ->decrement('occupied_weight_kg', $weightKg);
            }

            return $movement;
        });
    }

    /**
     * Correct the running quantity without a physical in/out event. The `delta`
     * attribute is signed: positive to increase, negative to decrease.
     *
     * @param  array<string, mixed>  $attributes  must include: delta (signed), reason
     * @throws ValidationException when the adjustment results in negative stock
     */
    public function adjust(StockLot $lot, array $attributes, ?int $performedBy): StockMovement
    {
        return DB::transaction(function () use ($lot, $attributes, $performedBy): StockMovement {
            $lot = StockLot::whereKey($lot->getKey())->lockForUpdate()->firstOrFail();

            $delta      = (float) $attributes['delta']; // signed
            $newBalance = (float) $lot->quantity + $delta;

            if ($newBalance < 0.0) {
                throw ValidationException::withMessages([
                    'delta' => sprintf(
                        'Adjustment would result in negative stock (%.3f + %.3f = %.3f).',
                        (float) $lot->quantity,
                        $delta,
                        $newBalance,
                    ),
                ]);
            }

            $movement = StockMovement::create([
                'lot_id'       => $lot->getKey(),
                'branch_id'    => $lot->branch_id,
                'type'         => StockMovementType::Adjustment->value,
                'quantity'     => $delta, // signed; negative = downward correction
                'weight_kg'    => $attributes['weight_kg'] ?? null,
                'balance_after' => $newBalance,
                'reference'    => $attributes['reference'] ?? null,
                'reason'       => $attributes['reason'],
                'performed_by' => $performedBy,
                'occurred_at'  => $attributes['occurred_at'] ?? now(),
                'metadata'     => $attributes['metadata'] ?? null,
            ]);

            $lot->quantity = $newBalance;
            $lot->status   = $newBalance <= 0.0 ? 'released'
                : ($newBalance < (float) $lot->initial_quantity ? 'partially_released' : 'in_storage');

            if ($newBalance <= 0.0) {
                $lot->released_at = now();
            }

            $lot->save();

            return $movement;
        });
    }

    /**
     * Relocate an entire lot to a different chamber / storage unit within the same
     * branch. Quantity is unchanged. Updates storage-unit occupancy on both ends.
     *
     * For partial relocation (splitting a lot) create a new lot instead.
     *
     * @param  array<string, mixed>  $attributes  must include: to_chamber_id
     * @throws ValidationException when the lot is not eligible for transfer
     */
    public function transfer(StockLot $lot, array $attributes, ?int $performedBy): StockMovement
    {
        return DB::transaction(function () use ($lot, $attributes, $performedBy): StockMovement {
            $lot = StockLot::whereKey($lot->getKey())->lockForUpdate()->firstOrFail();

            if (! $lot->isInStorage()) {
                throw ValidationException::withMessages([
                    'lot_id' => 'Only lots in storage can be transferred.',
                ]);
            }

            $fromChamberId  = $lot->chamber_id;
            $fromUnitId     = $lot->storage_unit_id;
            $toChamberId    = (int) $attributes['to_chamber_id'];
            $toUnitId       = isset($attributes['to_storage_unit_id'])
                ? (int) $attributes['to_storage_unit_id']
                : null;
            $currentQty     = (float) $lot->quantity;
            $currentWeightKg = $lot->weight_kg !== null ? (float) $lot->weight_kg : null;

            $movement = StockMovement::create([
                'lot_id'               => $lot->getKey(),
                'branch_id'            => $lot->branch_id,
                'type'                 => StockMovementType::Transfer->value,
                'quantity'             => $currentQty,
                'weight_kg'            => $currentWeightKg,
                'balance_after'        => $currentQty, // quantity unchanged by a transfer
                'from_chamber_id'      => $fromChamberId,
                'from_storage_unit_id' => $fromUnitId,
                'to_chamber_id'        => $toChamberId,
                'to_storage_unit_id'   => $toUnitId,
                'reference'            => $attributes['reference'] ?? null,
                'reason'               => $attributes['reason'] ?? null,
                'performed_by'         => $performedBy,
                'occurred_at'          => now(),
            ]);

            $lot->chamber_id      = $toChamberId;
            $lot->storage_unit_id = $toUnitId;
            $lot->save();

            // Handoff occupancy between storage units.
            if ($currentWeightKg !== null) {
                if ($fromUnitId !== null) {
                    StorageUnit::withoutBranchScope()
                        ->whereKey($fromUnitId)
                        ->decrement('occupied_weight_kg', $currentWeightKg);
                }

                if ($toUnitId !== null) {
                    StorageUnit::withoutBranchScope()
                        ->whereKey($toUnitId)
                        ->increment('occupied_weight_kg', $currentWeightKg);
                }
            }

            return $movement;
        });
    }
}
