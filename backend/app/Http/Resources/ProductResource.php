<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'code'            => $this->code,
            'name'            => $this->name,
            'category'        => $this->category,
            'unit_of_measure' => $this->unit_of_measure,
            'storage_band'    => [
                'temp_min_c' => $this->default_temp_min_c !== null ? (float) $this->default_temp_min_c : null,
                'temp_max_c' => $this->default_temp_max_c !== null ? (float) $this->default_temp_max_c : null,
            ],
            'shelf_life_days' => $this->shelf_life_days,
            'hs_code'         => $this->hs_code,
            'attributes'      => $this->attributes,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
