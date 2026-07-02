<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\Http\Requests\Concerns\ResolvesBranchInput;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Record a payment receipt. Optionally allocate it across one or more payable
 * invoices in the same request. Further allocations can be added later via
 * POST /payments/{payment}/allocate.
 */
class StorePaymentRequest extends FormRequest
{
    use ResolvesBranchInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->defaultBranchFromContext();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'branch_id'   => $this->branchRules($tenantId),
            'customer_id' => [
                'required', 'integer',
                Rule::exists('customers', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'method' => [
                'required',
                Rule::in(['cash', 'bkash', 'nagad', 'bank_transfer', 'card', 'cheque', 'adjustment']),
            ],
            'status' => [
                'nullable',
                Rule::in(['pending', 'completed', 'failed', 'refunded', 'cancelled']),
            ],
            'currency'      => ['nullable', 'string', 'size:3'],
            'amount_poisha' => ['required', 'integer', 'min:1'],
            'reference'     => ['nullable', 'string', 'max:255'],
            'gateway'       => ['nullable', 'string', 'max:64'],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'paid_at'       => ['nullable', 'date'],

            // Optional immediate allocations.
            'allocations'                      => ['nullable', 'array'],
            'allocations.*.invoice_id'         => [
                'required', 'integer',
                Rule::exists('invoices', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'allocations.*.amount_poisha'      => ['required', 'integer', 'min:1'],
        ];
    }
}
