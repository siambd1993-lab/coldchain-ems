<?php

declare(strict_types=1);

namespace App\Http\Requests\Device;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update a device's metadata or placement. The hardware UID never changes;
 * status transitions are limited to what an operator may set by hand.
 */
class UpdateDeviceRequest extends FormRequest
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
            'name'   => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['provisioning', 'online', 'offline', 'fault', 'decommissioned'])],
            'branch_id' => [
                'sometimes', 'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'chamber_id' => [
                'nullable', 'integer',
                Rule::exists('chambers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'model'            => ['nullable', 'string', 'max:120'],
            'manufacturer'     => ['nullable', 'string', 'max:120'],
            'firmware_version' => ['nullable', 'string', 'max:64'],
            'config'           => ['nullable', 'array'],
        ];
    }
}
