<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Small request-shaping helpers shared by the API controllers: consistent page
 * sizing and resolution of the branch a write should be stamped against.
 */
trait InteractsWithApiRequest
{
    /** Clamp the requested page size to a sane range (default 20, max 100). */
    protected function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        return min(max($request->integer('per_page', $default), 1), $max);
    }

    /**
     * Resolve the branch a branch-scoped write belongs to: the explicit
     * `branch_id` in the payload, else the request's active branch (`X-Branch-Id`).
     * Guarantees the principal may act in that branch.
     *
     * @throws ValidationException when no branch can be determined
     */
    protected function resolveBranchId(Request $request, TenantContext $context): int
    {
        $branchId = $request->integer('branch_id') ?: $context->activeBranchId();

        if ($branchId === null || $branchId === 0) {
            throw ValidationException::withMessages([
                'branch_id' => 'A branch is required. Send branch_id or the X-Branch-Id header.',
            ]);
        }

        if (! $context->canAccessBranch((int) $branchId)) {
            throw ValidationException::withMessages([
                'branch_id' => 'You do not have access to the requested branch.',
            ]);
        }

        return (int) $branchId;
    }
}
