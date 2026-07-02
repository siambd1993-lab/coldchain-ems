<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RatePlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RatePlan
 */
class RatePlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'code'                   => $this->code,
            'name'                   => $this->name,
            'billing_method'         => $this->billing_method,
            'rate_poisha'            => (int) $this->rate_poisha,
            'minimum_charge_poisha'  => (int) $this->minimum_charge_poisha,
            'unit_of_measure'        => $this->unit_of_measure,
            'grace_days'             => (int) $this->grace_days,
            'tax_rate'               => (float) $this->tax_rate,
            'is_active'              => (bool) $this->is_active,
            'metadata'               => $this->metadata,
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
        ];
    }
}
