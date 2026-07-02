<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial update of a depositor. Every field is `sometimes` — only what the
 * client sends is validated and changed. Unique checks ignore the current row.
 */
class UpdateCustomerRequest extends FormRequest
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
        $customerId = $this->route('customer')?->getKey();

        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:64',
                Rule::unique('customers', 'code')
                    ->ignore($customerId)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'type' => ['sometimes', 'required', Rule::in(['individual', 'business'])],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],

            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('customers', 'email')
                    ->ignore($customerId)
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

            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'blocked'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
