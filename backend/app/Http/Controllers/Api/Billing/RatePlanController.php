<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Concerns\InteractsWithApiRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\RatePlan\StoreRatePlanRequest;
use App\Http\Resources\RatePlanResource;
use App\Models\RatePlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tariff definitions. Tenant-scoped; not branch-restricted (rate plans span
 * the whole tenant). `is_active` flags plans available for assignment to lots.
 */
final class RatePlanController extends Controller
{
    use InteractsWithApiRequest;

    public function index(Request $request): AnonymousResourceCollection
    {
        $plans = RatePlan::query()
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return RatePlanResource::collection($plans);
    }

    public function store(StoreRatePlanRequest $request): JsonResponse
    {
        $plan = RatePlan::create($request->validated());

        return RatePlanResource::make($plan)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(RatePlan $rate_plan): RatePlanResource
    {
        return RatePlanResource::make($rate_plan);
    }

    public function update(StoreRatePlanRequest $request, RatePlan $rate_plan): RatePlanResource
    {
        $rate_plan->update($request->validated());

        return RatePlanResource::make($rate_plan);
    }

    public function destroy(RatePlan $rate_plan): JsonResponse
    {
        $rate_plan->delete();

        return response()->json(['data' => ['message' => 'Rate plan deleted.']]);
    }
}
