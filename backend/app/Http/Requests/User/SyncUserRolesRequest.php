<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Replace a user's role set. An empty array strips every role (the account can
 * still sign in but holds no permissions).
 */
class SyncUserRolesRequest extends FormRequest
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
            'role_ids'   => ['present', 'array'],
            'role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
