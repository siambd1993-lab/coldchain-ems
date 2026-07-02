<?php

use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\ResolveTenant;
use App\Support\ApiError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The API is stateless JSON; always negotiate JSON and attach a request id.
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        // Resolve tenant from the JWT/subdomain on every authenticated request,
        // then hard-assert a tenant is in scope before controllers run.
        $middleware->alias([
            'tenant.resolve' => ResolveTenant::class,
            'tenant'         => EnsureTenantContext::class,
            'permission'     => RequirePermission::class,
        ]);

        // Route-model binding must not run until the tenant is resolved: the
        // TenantScope global scope reads TenantContext, so a binding resolved
        // earlier would fetch rows with no tenant constraint (cross-tenant
        // read/write by ID). Laravel's default priority puts SubstituteBindings
        // ahead of unlisted custom middleware — pin ours before it explicitly.
        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            ResolveTenant::class,
            EnsureTenantContext::class,
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Uniform JSON error envelope for the whole API (see 07-api-design.md).
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // fall back to default rendering for non-API routes
            }

            return match (true) {
                $e instanceof ValidationException => ApiError::make(
                    'VALIDATION_ERROR', $e->getMessage(), 422, $e->errors(),
                ),
                $e instanceof AuthenticationException => ApiError::make(
                    'UNAUTHENTICATED', 'Authentication required.', 401,
                ),
                $e instanceof AuthorizationException => ApiError::make(
                    'FORBIDDEN', $e->getMessage() ?: 'This action is not authorized.', 403,
                ),
                // 404 for missing models AND cross-tenant lookups (avoid enumeration).
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiError::make(
                    'NOT_FOUND', 'Resource not found.', 404,
                ),
                $e instanceof ThrottleRequestsException => ApiError::make(
                    'RATE_LIMITED', 'Too many requests.', 429,
                ),
                $e instanceof HttpExceptionInterface => ApiError::make(
                    'HTTP_ERROR', $e->getMessage(), $e->getStatusCode(),
                ),
                default => ApiError::make(
                    'SERVER_ERROR',
                    app()->hasDebugModeEnabled() ? $e->getMessage() : 'Something went wrong.',
                    500,
                    app()->hasDebugModeEnabled()
                        ? ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()]
                        : null,
                ),
            };
        });
    })->create();
