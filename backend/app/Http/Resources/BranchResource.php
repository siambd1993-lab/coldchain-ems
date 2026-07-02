<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Branch
 */
class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'tenant_id' => $this->tenant_id,
            'code'      => $this->code,
            'name'      => $this->name,
            'status'    => $this->status,

            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city'          => $this->city,
            'district'      => $this->district,
            'division'      => $this->division,
            'postal_code'   => $this->postal_code,
            'country'       => $this->country,

            'latitude'  => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,

            'phone'    => $this->phone,
            'email'    => $this->email,
            'timezone' => $this->timezone,

            'chambers_count' => $this->whenCounted('chambers'),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
