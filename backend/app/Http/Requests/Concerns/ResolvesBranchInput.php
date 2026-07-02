<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\TenantContext;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Shared branch handling for branch-scoped create requests.
 *
 * A write into a branch-owned table needs a `branch_id`. Clients may pass one
 * explicitly, or rely on the request's active branch (`X-Branch-Id`). This trait
 * defaults the field from context and validates that (a) the branch belongs to
 * the acting tenant and (b) the principal is allowed to act in it.
 */
trait ResolvesBranchInput
{
    /** Fill `branch_id` from the active branch when the client omitted it. */
    protected function defaultBranchFromContext(): void
    {
        if (! $this->filled('branch_id')) {
            $active = app(TenantContext::class)->activeBranchId();

            if ($active !== null) {
                $this->merge(['branch_id' => $active]);
            }
        }
    }

    /**
     * Validation rules for a required, tenant-owned, accessible branch.
     *
     * @return array<int, mixed>
     */
    protected function branchRules(?int $tenantId): array
    {
        return [
            'required', 'integer',
            Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            static function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(TenantContext::class)->canAccessBranch((int) $value)) {
                    $fail('You do not have access to the requested branch.');
                }
            },
        ];
    }
}
