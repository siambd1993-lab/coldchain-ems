<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'type'           => $this->type,
            'name'           => $this->name,
            'contact_person' => $this->contact_person,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'status'         => $this->status,

            'branch_id'      => $this->branch_id,

            // KYC / compliance.
            'national_id'    => $this->national_id,
            'tax_id'         => $this->tax_id,
            'trade_license'  => $this->trade_license,

            // Address.
            'address_line1'  => $this->address_line1,
            'address_line2'  => $this->address_line2,
            'city'           => $this->city,
            'district'       => $this->district,
            'country'        => $this->country,

            // Money is stored and returned in poisha (1 BDT = 100 poisha).
            'credit_limit_poisha'    => (int) $this->credit_limit_poisha,
            'opening_balance_poisha' => (int) $this->opening_balance_poisha,
            'balance_poisha'         => (int) $this->balance_poisha,

            'has_portal_login' => $this->password !== null,
            'notes'            => $this->notes,

            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
