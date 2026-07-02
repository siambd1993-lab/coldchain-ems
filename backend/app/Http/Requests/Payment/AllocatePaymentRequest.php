<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Apply additional invoice allocations to an existing payment. The sum of new
 * allocations must not exceed the payment's current unallocated balance.
 */
class AllocatePaymentRequest extends FormRequest
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
            'allocations'                 => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id'    => [
                'required', 'integer',
                Rule::exists('invoices', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'allocations.*.amount_poisha' => ['required', 'integer', 'min:1'],
        ];
    }
}
