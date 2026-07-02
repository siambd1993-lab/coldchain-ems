<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Permission;
use App\Models\User;

/**
 * Request-scoped holder for "who is acting, and on whose data".
 *
 * Registered as a singleton (per request) in {@see \App\Providers\AppServiceProvider}.
 * The {@see \App\Http\Middleware\ResolveTenant} middleware boots it from the
 * authenticated principal; everything downstream — the {@see \App\Models\Scopes\TenantScope}
 * global scope, the {@see \App\Http\Middleware\RequirePermission} gate, controllers,
 * and jobs — reads tenancy and authorization state from here rather than
 * re-deriving it.
 *
 * Design notes:
 *  - `tenantId` is the hard row-scoping boundary. When null, no tenant has been
 *    resolved (unauthenticated, or platform-admin acting globally).
 *  - `branchIds` is the set of branches the principal may touch. Empty means
 *    "all branches within the tenant" (owner/admin). A non-empty list restricts
 *    branch-scoped reads/writes (branch manager, operator).
 *  - `activeBranchId` is the branch selected for the *current request* via the
 *    `X-Branch-Id` header; it must be a member of `branchIds` (or any branch when
 *    `branchIds` is empty).
 */
final class TenantContext
{
    private ?int $tenantId = null;

    private ?User $user = null;

    /** @var list<string> role slugs held by the principal */
    private array $roles = [];

    /** @var array<string,true> resolved permission slugs, as a set for O(1) checks */
    private array $permissions = [];

    /** @var list<int> branch ids the principal may access; empty = all in tenant */
    private array $branchIds = [];

    private ?int $activeBranchId = null;

    private bool $superAdmin = false;

    /**
     * Populate the context from an authenticated user and the authorization
     * facts resolved for this request. Permissions are passed in already
     * resolved (the database `roles.permissions` column is authoritative, so
     * both system and tenant-authored custom roles are honoured) rather than
     * re-derived from the Role enum here.
     *
     * @param  list<string>  $roles        role slugs held by the principal
     * @param  list<string>  $permissions  effective permission slugs
     * @param  list<int>     $branchIds     accessible branch ids (empty = all)
     * @param  bool          $superAdmin    bypass tenant scope & permission gate
     */
    public function boot(
        User $user,
        int $tenantId,
        array $roles,
        array $permissions,
        array $branchIds = [],
        bool $superAdmin = false,
    ): void {
        $this->user       = $user;
        $this->tenantId   = $tenantId;
        $this->roles      = array_values(array_unique($roles));
        $this->branchIds  = array_values(array_unique(array_map('intval', $branchIds)));
        $this->superAdmin = $superAdmin;

        $set = [];
        foreach ($permissions as $perm) {
            $set[$perm] = true;
        }
        $this->permissions = $set;
    }

    /** Reset — used between queued jobs that reuse a warm container. */
    public function forget(): void
    {
        $this->tenantId = null;
        $this->user = null;
        $this->roles = [];
        $this->permissions = [];
        $this->branchIds = [];
        $this->activeBranchId = null;
        $this->superAdmin = false;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    /**
     * Force a tenant id without a full principal — used by system/queue
     * contexts (e.g. telemetry ingest) that operate on one tenant's data
     * without an interactive user.
     */
    public function setTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function userId(): ?int
    {
        return $this->user?->getKey();
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->superAdmin;
    }

    public function hasRole(Role|string $role): bool
    {
        $slug = $role instanceof Role ? $role->value : $role;

        return in_array($slug, $this->roles, true);
    }

    /**
     * Does the principal hold the given permission?
     * Platform admins short-circuit to true.
     */
    public function can(Permission|string $permission): bool
    {
        if ($this->superAdmin) {
            return true;
        }

        $slug = $permission instanceof Permission ? $permission->value : $permission;

        return isset($this->permissions[$slug]);
    }

    public function cannot(Permission|string $permission): bool
    {
        return ! $this->can($permission);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return array_keys($this->permissions);
    }

    // ── Branch scoping ───────────────────────────────────────────────────

    /** @return list<int> */
    public function branchIds(): array
    {
        return $this->branchIds;
    }

    /** True when the principal is limited to a specific subset of branches. */
    public function isBranchRestricted(): bool
    {
        return $this->branchIds !== [];
    }

    public function canAccessBranch(int $branchId): bool
    {
        return ! $this->isBranchRestricted() || in_array($branchId, $this->branchIds, true);
    }

    public function activeBranchId(): ?int
    {
        return $this->activeBranchId;
    }

    /**
     * Select the branch for the current request. Returns false when the branch
     * is outside the principal's allowed set (caller should 403/404).
     */
    public function setActiveBranch(?int $branchId): bool
    {
        if ($branchId !== null && ! $this->canAccessBranch($branchId)) {
            return false;
        }

        $this->activeBranchId = $branchId;

        return true;
    }
}
