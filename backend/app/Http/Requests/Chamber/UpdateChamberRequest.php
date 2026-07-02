<?php

declare(strict_types=1);

namespace App\Http\Requests\Chamber;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial update of a chamber. `branch_id` is intentionally immutable — moving a
 * chamber between branches would strand its storage units and stock lots, so it
 * is not accepted here.
 */
class UpdateChamberRequest extends FormRequest
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
        $chamber = $this->route('chamber');
        $chamberId = $chamber?->getKey();
        $branchId = $chamber?->getAttribute('branch_id');

        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:64',
                Rule::unique('chambers', 'code')
                    ->ignore($chamberId)
                    ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'chamber_type' => [
                'sometimes', 'required',
                Rule::in(['freezer', 'chiller', 'cold_room', 'blast_freezer', 'ripening', 'ambient']),
            ],
            'status' => ['sometimes', 'required', Rule::in(['operational', 'maintenance', 'offline', 'defrost'])],

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
