<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add or update a line on a draft invoice. `tax_poisha` and `amount_poisha`
 * are calculated server-side — never send them.
 */
class StoreInvoiceLineRequest extends FormRequest
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
            'lot_id' => [
                'nullable', 'integer',
                Rule::exists('stock_lots', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'rate_plan_id' => [
                'nullable', 'integer',
                Rule::exists('rate_plans', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'description'       => ['required', 'string', 'max:500'],
            'quantity'          => ['required', 'numeric', 'min:0.001'],
            'unit'              => ['nullable', 'string', 'max:16'],
            'unit_price_poisha' => ['required', 'integer', 'min:0'],
            'discount_poisha'   => ['nullable', 'integer', 'min:0'],
            'tax_rate'          => ['nullable', 'numeric', 'between:0,100'],
            'period_start'      => ['nullable', 'date'],
            'period_end'        => ['nullable', 'date', 'after_or_equal:period_start'],
            'metadata'          => ['nullable', 'array'],
        ];
    }
}
