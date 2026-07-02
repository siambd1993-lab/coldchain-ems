<?php

declare(strict_types=1);

namespace App\Http\Requests\StorageUnit;

use App\Models\Chamber;
use App\Support\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a storage unit inside a chamber. The unit inherits its chamber's branch
 * (set by the controller), so no `branch_id` is accepted here. `code` is unique
 * within the chamber. `occupied_weight_kg` is maintained by the inventory
 * pipeline and is not client-settable.
 */
class StoreStorageUnitRequest extends FormRequest
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
        $chamberId = $this->integer('chamber_id');

        return [
            'chamber_id' => [
                'required', 'integer',
                Rule::exists('chambers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
                static function (string $attribute, mixed $value, Closure $fail): void {
                    // The chamber must live in a branch the caller may act in.
                    $branchId = Chamber::withoutBranchScope()->whereKey($value)->value('branch_id');

                    if ($branchId === null || ! app(TenantContext::class)->canAccessBranch((int) $branchId)) {
                        $fail('You do not have access to the selected chamber.');
                    }
                },
            ],

            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('storage_units', 'code')
                    ->where(fn ($q) => $q->where('chamber_id', $chamberId)->whereNull('deleted_at')),
            ],
            'label' => ['nullable', 'string', 'max:255'],
            'unit_type' => [
                'required',
                Rule::in(['rack', 'shelf', 'pallet_position', 'bin', 'floor_space', 'room']),
            ],
            'status' => ['nullable', Rule::in(['available', 'occupied', 'reserved', 'maintenance'])],

            'capacity_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'capacity_volume_m3' => ['nullable', 'numeric', 'min:0'],

            'grid_row' => ['nullable', 'string', 'max:32'],
            'grid_column' => ['nullable', 'string', 'max:32'],
            'level' => ['nullable', 'string', 'max:32'],
        ];
    }
}
