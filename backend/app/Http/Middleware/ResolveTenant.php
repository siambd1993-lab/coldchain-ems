<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiError;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Boots {@see TenantContext} from the authenticated principal.
 *
 * Runs *after* the auth guard (so a user is available) and *before* any
 * tenant-scoped work. Establishes:
 *   - the acting tenant (the user's own tenant, or — for platform admins — an
 *     optional `X-Tenant-Id` impersonation target),
 *   - the principal's roles and resolved permissions,
 *   - the accessible branch set and the request's active branch (`X-Branch-Id`).
 *
 * The database is treated as authoritative for roles/branches; short-lived JWT
 * claims are a cache, not a source of truth, so a revoked role takes effect on
 * the next request rather than at token expiry.
 */
final class ResolveTenant
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        // No authenticated user → nothing to resolve. Route-level `auth:api`
        // is responsible for rejecting unauthenticated access; a bare
        // tenant-scoped model query will simply return nothing.
        if (! $user instanceof User) {
            return $next($request);
        }

        // The JWT guard retrieves the user without its RBAC relations; load them
        // now so role/permission/branch resolution doesn't trip lazy-loading.
        $user->loadMissing(['roles', 'branches']);

        $tenantId = $this->resolveTenantId($request, $user);

        if ($tenantId === null && ! $user->isPlatformAdmin()) {
            // A normal user must always belong to a tenant.
            return ApiError::make(
                'tenant_unresolved',
                'Your account is not associated with an active tenant.',
                Response::HTTP_FORBIDDEN,
            );
        }

        $this->context->boot(
            user: $user,
            tenantId: $tenantId ?? 0,
            roles: $user->roleSlugs(),
            permissions: $user->effectivePermissionSlugs(),
            branchIds: $user->accessibleBranchIds(),
            superAdmin: $user->isPlatformAdmin(),
        );

        // Platform admin acting globally: clear the placeholder tenant id so
        // the tenant scope is bypassed entirely.
        if ($tenantId === null) {
            $this->context->setTenantId(null);
        }

        if (! $this->applyActiveBranch($request)) {
            return ApiError::make(
                'branch_forbidden',
                'You do not have access to the requested branch.',
                Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }

    /**
     * Determine which tenant this request operates on. Platform admins may
     * target any tenant via the `X-Tenant-Id` header; everyone else is pinned
     * to their own tenant.
     */
    private function resolveTenantId(Request $request, User $user): ?int
    {
        if ($user->isPlatformAdmin()) {
            $header = $request->headers->get('X-Tenant-Id');

            return $header !== null && $header !== '' ? (int) $header : null;
        }

        $own = $user->getAttribute('tenant_id');

        return $own !== null ? (int) $own : null;
    }

    /** Apply the per-request branch selection, if one was supplied. */
    private function applyActiveBranch(Request $request): bool
    {
        $header = $request->headers->get('X-Branch-Id');

        if ($header === null || $header === '') {
            return true;
        }

        return $this->context->setActiveBranch((int) $header);
    }
}
