<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a depositor. `tenant_id` is never accepted from the client — it is
 * stamped from the tenant context by the model. Uniqueness of `code`/`email` is
 * scoped to the tenant (two tenants may reuse the same customer code).
 */
class StoreCustomerRequest extends FormRequest
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
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('customers', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'type' => ['required', Rule::in(['individual', 'business'])],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],

            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('customers', 'email')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],

            'branch_id' => [
                'nullable', 'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],

            'national_id' => ['nullable', 'string', 'max:64'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'trade_license' => ['nullable', 'string', 'max:64'],

            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'size:2'],

            'credit_limit_poisha' => ['nullable', 'integer', 'min:0'],
            'opening_balance_poisha' => ['nullable', 'integer'],

            'status' => ['nullable', Rule::in(['active', 'inactive', 'blocked'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
