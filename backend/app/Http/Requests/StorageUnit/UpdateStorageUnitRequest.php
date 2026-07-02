<?php

declare(strict_types=1);

namespace App\Http\Requests\StorageUnit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial update of a storage unit. `chamber_id` (and thus branch) is immutable —
 * relocating a unit would strand any lots stored in it. `occupied_weight_kg` is
 * derived from inventory and never set directly.
 */
class UpdateStorageUnitRequest extends FormRequest
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
        $unit = $this->route('storage_unit');
        $unitId = $unit?->getKey();
        $chamberId = $unit?->getAttribute('chamber_id');

        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:64',
                Rule::unique('storage_units', 'code')
                    ->ignore($unitId)
                    ->where(fn ($q) => $q->where('chamber_id', $chamberId)->whereNull('deleted_at')),
            ],
            'label' => ['nullable', 'string', 'max:255'],
            'unit_type' => [
                'sometimes', 'required',
                Rule::in(['rack', 'shelf', 'pallet_position', 'bin', 'floor_space', 'room']),
            ],
            'status' => ['sometimes', 'required', Rule::in(['available', 'occupied', 'reserved', 'maintenance'])],

            'capacity_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'capacity_volume_m3' => ['nullable', 'numeric', 'min:0'],

            'grid_row' => ['nullable', 'string', 'max:32'],
            'grid_column' => ['nullable', 'string', 'max:32'],
            'level' => ['nullable', 'string', 'max:32'],
        ];
    }
}
