<?php

declare(strict_types=1);

namespace App\Http\Requests\RatePlan;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId    = app(TenantContext::class)->tenantId();
        $ratePlanId  = $this->route('rate_plan')?->getKey(); // null on create

        return [
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('rate_plans', 'code')
                    ->ignore($ratePlanId)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'billing_method' => [
                'required',
                Rule::in([
                    'per_kg_per_day', 'per_kg_per_month',
                    'per_slot_per_day', 'per_slot_per_month',
                    'per_pallet_per_day', 'per_pallet_per_month',
                    'flat_monthly',
                ]),
            ],
            'rate_poisha'           => ['required', 'integer', 'min:0'],
            'minimum_charge_poisha' => ['nullable', 'integer', 'min:0'],
            'unit_of_measure'       => ['nullable', 'string', 'max:16'],
            'grace_days'            => ['nullable', 'integer', 'min:0'],
            'tax_rate'              => ['nullable', 'numeric', 'between:0,100'],
            'is_active'             => ['nullable', 'boolean'],
            'metadata'              => ['nullable', 'array'],
        ];
    }
}
