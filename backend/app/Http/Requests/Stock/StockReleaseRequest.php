<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Record a stock-out (full or partial release to the depositor). The quantity is
 * the magnitude being released — always positive. The service validates it
 * against the lot's live balance.
 */
class StockReleaseRequest extends FormRequest
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
            'quantity'      => ['required', 'numeric', 'min:0.001'],
            'weight_kg'     => ['nullable', 'numeric', 'min:0'],
            'package_count' => ['nullable', 'integer', 'min:0'],
            'reference'     => ['nullable', 'string', 'max:100'],
            'reason'        => ['nullable', 'string', 'max:500'],
            'occurred_at'   => ['nullable', 'date'],
            'metadata'      => ['nullable', 'array'],
        ];
    }
}
