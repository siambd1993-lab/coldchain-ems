<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StockLot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockLot
 */
class StockLotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'lot_code'         => $this->lot_code,
            'status'           => $this->status,
            'branch_id'        => $this->branch_id,
            'customer_id'      => $this->customer_id,
            'product_id'       => $this->product_id,
            'chamber_id'       => $this->chamber_id,
            'storage_unit_id'  => $this->storage_unit_id,
            'rate_plan_id'     => $this->rate_plan_id,
            'description'      => $this->description,
            'unit_of_measure'  => $this->unit_of_measure,

            // Quantity snapshot (running total from ledger). Decimal strings —
            // the SPA types expect them and floats would lose precision.
            'initial_quantity' => $this->initial_quantity,
            'quantity'         => $this->quantity,
            'initial_weight_kg' => $this->initial_weight_kg !== null ? (float) $this->initial_weight_kg : null,
            'weight_kg'        => $this->weight_kg !== null ? (float) $this->weight_kg : null,
            'package_count'    => $this->package_count,

            'grade'            => $this->grade,
            'marks'            => $this->marks,

            'received_at'          => $this->received_at?->toIso8601String(),
            'expected_release_at'  => $this->expected_release_at?->toDateString(),
            'released_at'          => $this->released_at?->toIso8601String(),
            'expiry_date'          => $this->expiry_date?->toDateString(),
            'days_in_storage'      => $this->daysInStorage(),

            'metadata'         => $this->metadata,

            // Embedded relations — only included when loaded by the controller.
            'customer'         => CustomerResource::make($this->whenLoaded('customer')),
            'product'          => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'code' => $this->product->code,
                'name' => $this->product->name,
            ]),
            'movements'        => StockMovementResource::collection($this->whenLoaded('movements')),

            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
