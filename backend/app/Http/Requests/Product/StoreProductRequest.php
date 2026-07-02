<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
        $productId = $this->route('product')?->getKey(); // null on create

        return [
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('products', 'code')
                    ->ignore($productId)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'name'     => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit_of_measure' => [
                'nullable',
                Rule::in(['kg', 'ton', 'crate', 'bag', 'carton', 'piece', 'pallet']),
            ],
            'default_temp_min_c' => ['nullable', 'numeric', 'between:-99.99,99.99'],
            'default_temp_max_c' => ['nullable', 'numeric', 'between:-99.99,99.99', 'gte:default_temp_min_c'],
            'shelf_life_days'    => ['nullable', 'integer', 'min:1'],
            'hs_code'            => ['nullable', 'string', 'max:32'],
            'attributes'         => ['nullable', 'array'],
        ];
    }
}
