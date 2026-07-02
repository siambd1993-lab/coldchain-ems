<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Chamber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Chamber
 */
class ChamberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'branch_id'    => $this->branch_id,
            'code'         => $this->code,
            'name'         => $this->name,
            'chamber_type' => $this->chamber_type,
            'status'       => $this->status,

            'capacity' => [
                'weight_kg' => $this->capacity_weight_kg !== null ? (float) $this->capacity_weight_kg : null,
                'volume_m3' => $this->capacity_volume_m3 !== null ? (float) $this->capacity_volume_m3 : null,
                'slots'     => $this->capacity_slots,
                'area_sqft' => $this->area_sqft !== null ? (float) $this->area_sqft : null,
            ],

            'target_band' => [
                'temp_min_c'   => $this->target_temp_min_c !== null ? (float) $this->target_temp_min_c : null,
                'temp_max_c'   => $this->target_temp_max_c !== null ? (float) $this->target_temp_max_c : null,
                'humidity_min' => $this->target_humidity_min !== null ? (float) $this->target_humidity_min : null,
                'humidity_max' => $this->target_humidity_max !== null ? (float) $this->target_humidity_max : null,
            ],

            'current' => [
                'temp_c'      => $this->current_temp_c !== null ? (float) $this->current_temp_c : null,
                'humidity'    => $this->current_humidity !== null ? (float) $this->current_humidity : null,
                'observed_at' => $this->readings_updated_at?->toIso8601String(),
            ],

            'floor'      => $this->floor,
            'zone'       => $this->zone,

            'storage_units_count' => $this->whenCounted('storageUnits'),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
