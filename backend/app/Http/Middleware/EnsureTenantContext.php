<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiError;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guard for routes that *require* an established tenant.
 *
 * {@see ResolveTenant} sets up the context; this middleware asserts it is
 * present. Use it on tenant-scoped route groups so that a platform admin who
 * forgot the `X-Tenant-Id` header (and therefore has no tenant) gets a clear
 * 400 instead of silently querying nothing.
 */
final class EnsureTenantContext
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->hasTenant()) {
            return ApiError::make(
                'tenant_required',
                'A tenant must be selected for this request. Provide the X-Tenant-Id header.',
                Response::HTTP_BAD_REQUEST,
            );
        }

        return $next($request);
    }
}
