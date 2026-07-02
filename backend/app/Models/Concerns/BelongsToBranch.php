<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as branch-owned (a facility location within a tenant).
 *
 * Adds the {@see BranchScope} for per-branch isolation and stamps `branch_id`
 * from the request's active branch on insert when the caller left it blank.
 * Compose with {@see BelongsToTenant} — a branch always lives inside a tenant.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope());

        static::creating(function ($model): void {
            $key = $model->getBranchKeyName();

            if ($model->getAttribute($key) === null) {
                $branchId = app(TenantContext::class)->activeBranchId();
                if ($branchId !== null) {
                    $model->setAttribute($key, $branchId);
                }
            }
        });
    }

    public function getBranchKeyName(): string
    {
        return 'branch_id';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, $this->getBranchKeyName());
    }

    public static function withoutBranchScope(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()->withoutGlobalScope(BranchScope::class);
    }
}
