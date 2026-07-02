<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a staff account. `tenant_id` is stamped from the tenant context by the
 * model; `is_platform_admin` is never accepted from the client. Role and branch
 * ids must belong to the acting tenant (the global platform_admin role row has
 * a null tenant_id, so it can never be assigned here).
 */
class StoreUserRequest extends FormRequest
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

        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'phone'    => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'status'   => ['nullable', Rule::in(['active', 'suspended'])],

            'branch_id' => [
                'nullable', 'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'branch_ids'   => ['nullable', 'array'],
            'branch_ids.*' => [
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],

            'role_ids'   => ['nullable', 'array'],
            'role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
