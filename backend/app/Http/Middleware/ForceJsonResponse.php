<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees the API always negotiates JSON.
 *
 * Forcing `Accept: application/json` makes Laravel's exception handler emit JSON
 * problem envelopes (never an HTML error page) even when a client forgets the
 * header — important for mobile/IoT clients and curl. Also stamps a
 * per-request id used across logs and the error envelope's `request_id`.
 */
final class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        // Correlation id: honour an inbound X-Request-Id (from the edge/proxy)
        // or mint one. Exposed back to the client via the response header.
        $requestId = $request->headers->get('X-Request-Id')
            ?: (string) \Illuminate\Support\Str::uuid();

        $request->attributes->set('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
