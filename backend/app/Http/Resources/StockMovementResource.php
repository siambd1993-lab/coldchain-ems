<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Enums\StockMovementType $type */
        $type = $this->type;

        return [
            'id'            => $this->id,
            'lot_id'        => $this->lot_id,
            'type'          => $type->value,
            'type_label'    => $type->label(),
            'type_sign'     => $type->sign(), // +1 / -1 / 0

            // Decimal strings, matching StockLotResource and the SPA types.
            'quantity'      => $this->quantity,
            'weight_kg'     => $this->weight_kg,
            'package_count' => $this->package_count,
            'balance_after' => $this->balance_after,

            'from_chamber_id'      => $this->from_chamber_id,
            'from_storage_unit_id' => $this->from_storage_unit_id,
            'to_chamber_id'        => $this->to_chamber_id,
            'to_storage_unit_id'   => $this->to_storage_unit_id,

            'reference'    => $this->reference,
            'reason'       => $this->reason,

            'performed_by' => $this->performed_by,
            'performer'    => $this->whenLoaded('performer', fn () => [
                'id'   => $this->performer->id,
                'name' => $this->performer->name,
            ]),

            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
