<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StorageUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StorageUnit
 */
class StorageUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $capacity = $this->capacity_weight_kg !== null ? (float) $this->capacity_weight_kg : null;
        $occupied = (float) $this->occupied_weight_kg;

        return [
            'id'         => $this->id,
            'branch_id'  => $this->branch_id,
            'chamber_id' => $this->chamber_id,
            'code'       => $this->code,
            'label'      => $this->label,
            'unit_type'  => $this->unit_type,
            'status'     => $this->status,

            'capacity_weight_kg' => $capacity,
            'capacity_volume_m3' => $this->capacity_volume_m3 !== null ? (float) $this->capacity_volume_m3 : null,
            'occupied_weight_kg' => $occupied,
            'available_weight_kg' => $capacity !== null ? max(0.0, $capacity - $occupied) : null,

            'grid_row'    => $this->grid_row,
            'grid_column' => $this->grid_column,
            'level'       => $this->level,

            'chamber' => ChamberResource::make($this->whenLoaded('chamber')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
