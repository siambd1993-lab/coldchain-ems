<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\InteractsWithApiRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chamber\StoreChamberRequest;
use App\Http\Requests\Chamber\UpdateChamberRequest;
use App\Http\Resources\ChamberResource;
use App\Models\Chamber;
use App\Models\StockLot;
use App\Services\AuditLogger;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Temperature-controlled rooms within a branch. Rows are isolated by both the
 * tenant and branch global scopes, so the listing already reflects the caller's
 * active branch (or accessible branch set).
 */
final class ChamberController extends Controller
{
    use InteractsWithApiRequest;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $chambers = Chamber::query()
            ->withCount('storageUnits')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('chamber_type'), fn ($q) => $q->where('chamber_type', $request->string('chamber_type')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->orderBy('code')
            ->paginate($this->perPage($request));

        return ChamberResource::collection($chambers);
    }

    public function store(StoreChamberRequest $request): JsonResponse
    {
        $chamber = Chamber::create($request->validated());

        $this->audit->log('chamber.created', $chamber, [
            'description' => "Chamber {$chamber->code} created.",
            'new' => $chamber->only(['code', 'name', 'chamber_type', 'status', 'branch_id']),
        ]);

        return ChamberResource::make($chamber)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Chamber $chamber): ChamberResource
    {
        return ChamberResource::make($chamber->loadCount('storageUnits'));
    }

    public function update(UpdateChamberRequest $request, Chamber $chamber): ChamberResource
    {
        $before = $chamber->only(['code', 'name', 'chamber_type', 'status']);

        $chamber->update($request->validated());

        $this->audit->log('chamber.updated', $chamber, [
            'description' => "Chamber {$chamber->code} updated.",
            'old' => $before,
            'new' => $chamber->only(['code', 'name', 'chamber_type', 'status']),
        ]);

        return ChamberResource::make($chamber->loadCount('storageUnits'));
    }

    public function destroy(Chamber $chamber): JsonResponse
    {
        $hasActiveLots = StockLot::query()
            ->where('chamber_id', $chamber->getKey())
            ->whereIn('status', ['in_storage', 'partially_released'])
            ->exists();

        if ($hasActiveLots) {
            return ApiError::make(
                'chamber_has_active_stock',
                'This chamber still holds goods and cannot be deleted.',
                Response::HTTP_CONFLICT,
            );
        }

        $chamber->delete();

        $this->audit->log('chamber.deleted', $chamber, [
            'description' => "Chamber {$chamber->code} deleted.",
        ]);

        return response()->json(['data' => ['message' => 'Chamber deleted.']]);
    }
}
