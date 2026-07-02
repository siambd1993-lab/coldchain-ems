<?php

declare(strict_types=1);

namespace App\Http\Requests\Device;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Register an IoT device. The hardware UID is unique per tenant; the chamber,
 * when given, must belong to the same tenant.
 */
class StoreDeviceRequest extends FormRequest
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
            'device_uid' => [
                'required', 'string', 'max:100',
                Rule::unique('devices', 'device_uid')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'name'        => ['required', 'string', 'max:255'],
            'device_type' => ['required', Rule::in([
                'sensor', 'gateway', 'plc', 'bms', 'inverter', 'energy_meter', 'controller',
            ])],
            'protocol' => ['nullable', Rule::in(['mqtt', 'modbus_tcp', 'modbus_rtu', 'rs485', 'http', 'snmp'])],
            'branch_id' => [
                'required', 'integer',
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
