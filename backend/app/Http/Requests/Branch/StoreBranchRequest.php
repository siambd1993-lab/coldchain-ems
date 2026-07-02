<?php

declare(strict_types=1);

namespace App\Http\Requests\Branch;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a branch (physical facility) for the current tenant. `code` is
 * unique within the tenant. `tenant_id` is auto-stamped by {@see \App\Models\Concerns\BelongsToTenant}
 * and is never accepted from the client.
 */
class StoreBranchRequest extends FormRequest
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
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('branches', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'name'   => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'under_maintenance'])],

            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:120'],
            'district'      => ['nullable', 'string', 'max:120'],
            'division'      => ['nullable', 'string', 'max:120'],
            'postal_code'   => ['nullable', 'string', 'max:16'],
            'country'       => ['nullable', 'string', 'size:2'],

            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'phone'    => ['nullable', 'string', 'max:32'],
            'email'    => ['nullable', 'email', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ];
    }
}
