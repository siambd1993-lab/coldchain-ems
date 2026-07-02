<?php

declare(strict_types=1);

namespace App\Http\Requests\Role;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update a custom role's name, description or permission set. The slug never
 * changes (it may be referenced by integrations); system roles are immutable
 * and rejected in the controller before validation matters.
 */
class UpdateRoleRequest extends FormRequest
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
            'name'          => ['sometimes', 'required', 'string', 'max:100'],
            'description'   => ['nullable', 'string', 'max:500'],
            'permissions'   => ['sometimes', 'required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
        ];
    }
}
