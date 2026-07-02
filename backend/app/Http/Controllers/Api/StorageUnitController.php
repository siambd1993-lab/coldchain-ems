<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\InteractsWithApiRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorageUnit\StoreStorageUnitRequest;
use App\Http\Requests\StorageUnit\UpdateStorageUnitRequest;
use App\Http\Resources\StorageUnitResource;
use App\Models\Chamber;
use App\Models\StockLot;
use App\Models\StorageUnit;
use App\Services\AuditLogger;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rentable subdivisions of a chamber. A unit's branch is inherited from its
 * chamber; both tenant and branch scopes isolate the listing.
 */
final class StorageUnitController extends Controller
{
    use InteractsWithApiRequest;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $units = StorageUnit::query()
            ->when($request->boolean('include_chamber'), fn ($q) => $q->with('chamber'))
            ->when($request->filled('chamber_id'), fn ($q) => $q->where('chamber_id', $request->integer('chamber_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('unit_type'), fn ($q) => $q->where('unit_type', $request->string('unit_type')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('code', 'like', $term)->orWhere('label', 'like', $term));
            })
            ->orderBy('code')
            ->paginate($this->perPage($request));

        return StorageUnitResource::collection($units);
    }

    public function store(StoreStorageUnitRequest $request): JsonResponse
    {
        $data = $request->validated();

        // The unit's branch is authoritative from its chamber, not the payload.
        // Access to that chamber was already enforced by the form request.
        $chamber = Chamber::withoutBranchScope()->findOrFail($data['chamber_id']);

        $unit = StorageUnit::create($data + ['branch_id' => $chamber->branch_id]);

        $this->audit->log('storage_unit.created', $unit, [
            'description' => "Storage unit {$unit->code} created in chamber {$chamber->code}.",
            'new' => $unit->only(['code', 'unit_type', 'status', 'chamber_id', 'branch_id']),
        ]);

        return StorageUnitResource::make($unit)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(StorageUnit $storage_unit): StorageUnitResource
    {
        return StorageUnitResource::make($storage_unit->load('chamber'));
    }

    public function update(UpdateStorageUnitRequest $request, StorageUnit $storage_unit): StorageUnitResource
    {
        $before = $storage_unit->only(['code', 'label', 'unit_type', 'status']);

        $storage_unit->update($request->validated());

        $this->audit->log('storage_unit.updated', $storage_unit, [
            'description' => "Storage unit {$storage_unit->code} updated.",
            'old' => $before,
            'new' => $storage_unit->only(['code', 'label', 'unit_type', 'status']),
        ]);

        return StorageUnitResource::make($storage_unit->load('chamber'));
    }

    public function destroy(StorageUnit $storage_unit): JsonResponse
    {
        $hasActiveLots = StockLot::query()
            ->where('storage_unit_id', $storage_unit->getKey())
            ->whereIn('status', ['in_storage', 'partially_released'])
            ->exists();

        if ($hasActiveLots) {
            return ApiError::make(
                'storage_unit_occupied',
                'This storage unit currently holds goods and cannot be deleted.',
                Response::HTTP_CONFLICT,
            );
        }

        $storage_unit->delete();

        $this->audit->log('storage_unit.deleted', $storage_unit, [
            'description' => "Storage unit {$storage_unit->code} deleted.",
        ]);

        return response()->json(['data' => ['message' => 'Storage unit deleted.']]);
    }
}
