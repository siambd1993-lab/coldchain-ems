<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\InteractsWithApiRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\AuditLogger;
use App\Support\ApiError;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Physical cold-storage facilities belonging to the tenant. Unlike chambers
 * and storage units, `Branch` only carries the tenant global scope (it *is*
 * the unit of branch scoping, not a thing scoped by it) — so branch
 * restriction is applied manually here from {@see TenantContext::branchIds()}.
 */
final class BranchController extends Controller
{
    use InteractsWithApiRequest;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request, TenantContext $context): AnonymousResourceCollection
    {
        $branches = Branch::query()
            ->withCount('chambers')
            ->when(
                $context->isBranchRestricted(),
                fn ($q) => $q->whereIn('id', $context->branchIds()),
            )
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->orderBy('code')
            ->paginate($this->perPage($request));

        return BranchResource::collection($branches);
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->validated());

        $this->audit->log('branch.created', $branch, [
            'description' => "Branch {$branch->code} created.",
            'new' => $branch->only(['code', 'name', 'status']),
        ]);

        return BranchResource::make($branch)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Branch $branch): BranchResource
    {
        return BranchResource::make($branch->loadCount('chambers'));
    }

    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $before = $branch->only(['code', 'name', 'status']);

        $branch->update($request->validated());

        $this->audit->log('branch.updated', $branch, [
            'description' => "Branch {$branch->code} updated.",
            'old' => $before,
            'new' => $branch->only(['code', 'name', 'status']),
        ]);

        return BranchResource::make($branch->loadCount('chambers'));
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $hasChambers = $branch->chambers()->exists();

        if ($hasChambers) {
            return ApiError::make(
                'branch_has_chambers',
                'This branch still has chambers and cannot be deleted.',
                Response::HTTP_CONFLICT,
            );
        }

        $branch->delete();

        $this->audit->log('branch.deleted', $branch, [
            'description' => "Branch {$branch->code} deleted.",
        ]);

        return response()->json(['data' => ['message' => 'Branch deleted.']]);
    }
}
