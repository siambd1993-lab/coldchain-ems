<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that constrains every query on a tenant-owned model to the
 * tenant resolved for the current request.
 *
 * Behaviour:
 *  - When a tenant is present in {@see TenantContext}, add
 *    `WHERE {table}.tenant_id = :id`. This is the row-security boundary that
 *    makes cross-tenant reads impossible by default.
 *  - When no tenant is present (console commands, seeders, system jobs,
 *    platform-admin acting globally), the scope adds no constraint. HTTP
 *    requests always pass through ResolveTenant first, so "no tenant" only
 *    happens in trusted contexts.
 *
 * The constraint is intentionally *not* applied when the developer opts out via
 * `Model::withoutTenancy()` (see {@see \App\Models\Concerns\BelongsToTenant}).
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if (! $context->hasTenant()) {
            return;
        }

        $builder->where(
            $model->getTable().'.'.$model->getTenantKeyName(),
            $context->tenantId(),
        );
    }
}
