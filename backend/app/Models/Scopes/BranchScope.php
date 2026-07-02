<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that narrows branch-owned models to the branches the current
 * principal may access. Layered on top of {@see TenantScope}.
 *
 * Resolution order:
 *  1. No tenant in context (system/console) → no constraint.
 *  2. Platform admin → no constraint.
 *  3. A branch is explicitly selected for the request (`X-Branch-Id`) → pin to
 *     exactly that branch.
 *  4. Principal is branch-restricted (branch manager / operator) → limit to
 *     their allowed branch set.
 *  5. Otherwise (owner / admin) → all branches within the tenant.
 */
final class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if (! $context->hasTenant() || $context->isPlatformAdmin()) {
            return;
        }

        $column = $model->getTable().'.'.$model->getBranchKeyName();

        if (($active = $context->activeBranchId()) !== null) {
            $builder->where($column, $active);

            return;
        }

        if ($context->isBranchRestricted()) {
            $builder->whereIn($column, $context->branchIds());
        }
    }
}
