<?php

declare(strict_types=1);

namespace App\Http\Requests\Chamber;

use App\Http\Requests\Concerns\ResolvesBranchInput;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a chamber inside a branch. `code` is unique within its branch. The
 * set-point band is optional but, when both bounds are given, min must not
 * exceed max.
 */
class StoreChamberRequest extends FormRequest
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
        $branchId = $this->integer('branch_id');

        return [
            'branch_id' => $this->branchRules($tenantId),

            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('chambers', 'code')
                    ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'chamber_type' => [
                'required',
                Rule::in(['freezer', 'chiller', 'cold_room', 'blast_freezer', 'ripening', 'ambient']),
            ],
            'status' => ['nullable', Rule::in(['operational', 'maintenance', 'offline', 'defrost'])],

            'capacity_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'capacity_volume_m3' => ['nullable', 'numeric', 'min:0'],
            'capacity_slots' => ['nullable', 'integer', 'min:0'],
            'area_sqft' => ['nullable', 'numeric', 'min:0'],

            'target_temp_min_c' => ['nullable', 'numeric', 'between:-99.99,99.99'],
            'target_temp_max_c' => ['nullable', 'numeric', 'between:-99.99,99.99', 'gte:target_temp_min_c'],
            'target_humidity_min' => ['nullable', 'numeric', 'between:0,100'],
            'target_humidity_max' => ['nullable', 'numeric', 'between:0,100', 'gte:target_humidity_min'],

            'floor' => ['nullable', 'string', 'max:64'],
            'zone' => ['nullable', 'string', 'max:64'],
        ];
    }
}
