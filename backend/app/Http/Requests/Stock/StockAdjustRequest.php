<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Record a quantity correction (weighbridge recount, damage write-off, etc.).
 * `delta` is signed: positive to increase stock, negative to decrease it.
 * A mandatory `reason` is required for the audit trail.
 */
class StockAdjustRequest extends FormRequest
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
        return [
            'delta'       => ['required', 'numeric'], // signed; not zero
            'weight_kg'   => ['nullable', 'numeric'],
            'reason'      => ['required', 'string', 'min:5', 'max:500'],
            'reference'   => ['nullable', 'string', 'max:100'],
            'occurred_at' => ['nullable', 'date'],
            'metadata'    => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required for stock adjustments.',
            'reason.min'      => 'The reason must be at least 5 characters.',
        ];
    }
}
