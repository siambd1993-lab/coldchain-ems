<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiError;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorizes a request against the principal's resolved permissions.
 *
 * Usage in routes:
 *   ->middleware('permission:customers.create')          // single
 *   ->middleware('permission:billing.manage,billing.view') // ANY of these
 *
 * Any-of semantics keep the common "view or manage" gate a one-liner. For
 * AND semantics, stack two `permission:` middleware on the route — each is a
 * separate check and both must pass.
 *
 * Platform admins always pass (handled inside {@see TenantContext::can()}).
 */
final class RequirePermission
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if ($permissions === []) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if ($this->context->can($permission)) {
                return $next($request);
            }
        }

        return ApiError::make(
            'forbidden',
            'You do not have permission to perform this action.',
            Response::HTTP_FORBIDDEN,
            ['required_any' => array_values($permissions)],
        );
    }
}
