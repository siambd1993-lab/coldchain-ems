<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use App\Models\Chamber;
use App\Support\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Relocate an entire lot to a different chamber (and optionally a different
 * storage unit) within the same branch. Quantity is unchanged; occupancy is
 * handed off between units by the service.
 */
class StockTransferRequest extends FormRequest
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
        $tenantId  = app(TenantContext::class)->tenantId();
        $toChamberId = $this->integer('to_chamber_id') ?: null;

        return [
            'to_chamber_id' => [
                'required', 'integer',
                Rule::exists('chambers', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
                static function (string $attribute, mixed $value, Closure $fail): void {
                    $branchId = Chamber::withoutBranchScope()->whereKey($value)->value('branch_id');

                    if ($branchId === null || ! app(TenantContext::class)->canAccessBranch((int) $branchId)) {
                        $fail('You do not have access to the selected destination chamber.');
                    }
                },
            ],

            'to_storage_unit_id' => [
                'nullable', 'integer',
                Rule::exists('storage_units', 'id')
                    ->where(fn ($q) => $q->when(
                        $toChamberId !== null,
                        fn ($sub) => $sub->where('chamber_id', $toChamberId),
                    )->whereNull('deleted_at')),
            ],

            'reason'    => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
