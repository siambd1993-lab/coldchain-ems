<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use App\Http\Requests\Concerns\ResolvesBranchInput;
use App\Models\Chamber;
use App\Support\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a stock intake (goods arriving at the facility). The lot's branch is
 * required — it may be supplied via `branch_id`, defaulted from the active branch
 * header, or derived from the selected chamber's branch if chamber_id is provided.
 *
 * A stock_in movement is created automatically by {@see StockService::intake()};
 * callers do not send movement data separately.
 */
class StoreStockLotRequest extends FormRequest
{
    use ResolvesBranchInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Prefer the branch the caller passed directly; fall back to the active
        // branch header; fall back to the chamber's branch (resolved after validation).
        $this->defaultBranchFromContext();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId  = app(TenantContext::class)->tenantId();
        $branchId  = $this->integer('branch_id') ?: null;
        $chamberId = $this->integer('chamber_id') ?: null;

        return [
            'branch_id' => $this->branchRules($tenantId),

            // Optional: StockService generates a sequential per-branch code
            // when the caller doesn't supply one.
            'lot_code' => [
                'nullable', 'string', 'max:100',
                Rule::unique('stock_lots', 'lot_code')
                    ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at')),
            ],

            'customer_id' => [
                'required', 'integer',
                Rule::exists('customers', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],

            'product_id' => [
                'nullable', 'integer',
                Rule::exists('products', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],

            'chamber_id' => [
                'nullable', 'integer',
                Rule::exists('chambers', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
                // Ensure the chamber lives in the validated branch.
                static function (string $attribute, mixed $value, Closure $fail) use ($branchId): void {
                    if ($value === null) {
                        return;
                    }

                    $chamberBranch = Chamber::withoutBranchScope()->whereKey($value)->value('branch_id');

                    if ($branchId !== null && $chamberBranch !== $branchId) {
                        $fail('The selected chamber does not belong to the specified branch.');
                    }

                    if (! app(TenantContext::class)->canAccessBranch((int) $chamberBranch)) {
                        $fail('You do not have access to the selected chamber.');
                    }
                },
            ],

            'storage_unit_id' => [
                'nullable', 'integer',
                Rule::exists('storage_units', 'id')
                    ->where(fn ($q) => $q->when(
                        $chamberId !== null,
                        fn ($sub) => $sub->where('chamber_id', $chamberId),
                    )->whereNull('deleted_at')),
            ],

            'rate_plan_id' => [
                'nullable', 'integer',
                Rule::exists('rate_plans', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],

            'description'  => ['nullable', 'string', 'max:500'],
            'unit_of_measure' => [
                'nullable',
                Rule::in(['kg', 'ton', 'crate', 'bag', 'carton', 'piece', 'pallet']),
            ],

            'quantity'      => ['required', 'numeric', 'min:0.001'],
            'weight_kg'     => ['nullable', 'numeric', 'min:0'],
            'package_count' => ['nullable', 'integer', 'min:0'],

            'grade'  => ['nullable', 'string', 'max:64'],
            'marks'  => ['nullable', 'string', 'max:255'],

            'received_at'         => ['nullable', 'date'],
            'expected_release_at' => ['nullable', 'date', 'after_or_equal:received_at'],
            'expiry_date'         => ['nullable', 'date'],

            'reference' => ['nullable', 'string', 'max:100'],
            'metadata'  => ['nullable', 'array'],
        ];
    }
}
