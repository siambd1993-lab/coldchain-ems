<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\InteractsWithApiRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Device\StoreDeviceRequest;
use App\Http\Requests\Device\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * IoT device registry (sensors, meters, controllers). Rows are tenant- and
 * branch-scoped like the rest of the facility structure. Live ingestion runs
 * through the MQTT pipeline; these endpoints manage the registry itself.
 */
final class DeviceController extends Controller
{
    use InteractsWithApiRequest;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $devices = Device::query()
            ->with(['chamber:id,name,code', 'channels'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('device_type'), fn ($q) => $q->where('device_type', $request->string('device_type')))
            ->when($request->filled('chamber_id'), fn ($q) => $q->where('chamber_id', $request->integer('chamber_id')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)
                    ->orWhere('device_uid', 'like', $term));
            })
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return DeviceResource::collection($devices);
    }

    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $device = Device::create($request->validated());

        $this->audit->log('device.registered', $device, [
            'description' => "Device {$device->device_uid} registered.",
            'new' => $device->only(['device_uid', 'name', 'device_type', 'status']),
        ]);

        return DeviceResource::make($device->load(['chamber:id,name,code', 'channels']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Device $device): DeviceResource
    {
        return DeviceResource::make($device->load(['chamber:id,name,code', 'channels']));
    }

    public function update(UpdateDeviceRequest $request, Device $device): DeviceResource
    {
        $before = $device->only(['name', 'status', 'chamber_id']);

        $device->update($request->validated());

        $this->audit->log('device.updated', $device, [
            'description' => "Device {$device->device_uid} updated.",
            'old' => $before,
            'new' => $device->only(['name', 'status', 'chamber_id']),
        ]);

        return DeviceResource::make($device->load(['chamber:id,name,code', 'channels']));
    }

    public function destroy(Device $device): JsonResponse
    {
        $device->delete();

        $this->audit->log('device.decommissioned', $device, [
            'description' => "Device {$device->device_uid} removed from the registry.",
        ]);

        return response()->json(['data' => ['message' => 'Device deleted.']]);
    }
}
