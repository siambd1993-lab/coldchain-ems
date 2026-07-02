<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update a staff account's profile, status, password or branch access. Role
 * changes go through the dedicated sync endpoint (permission users.assign_roles).
 */
class UpdateUserRequest extends FormRequest
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
            'name'  => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'email', 'max:255',
                Rule::unique('users', 'email')
                    ->ignore($this->route('user'))
                    ->whereNull('deleted_at'),
            ],
            'phone'    => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'status'   => ['sometimes', Rule::in(['active', 'suspended'])],

            'branch_id' => [
                'nullable', 'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'branch_ids'   => ['sometimes', 'array'],
            'branch_ids.*' => [
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
