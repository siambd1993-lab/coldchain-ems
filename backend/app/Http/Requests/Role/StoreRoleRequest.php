<?php

declare(strict_types=1);

namespace App\Http\Requests\Role;

use App\Enums\Permission;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Create a tenant-authored (custom) role. The slug is derived from the name and
 * is immutable afterwards; every permission must be a declared Permission slug.
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('name')) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'), '_')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'slug')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'description'   => ['nullable', 'string', 'max:500'],
            'permissions'   => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'A role with this name already exists.',
            'permissions.*.in' => 'Unknown permission slug.',
        ];
    }
}
