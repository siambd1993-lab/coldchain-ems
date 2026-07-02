<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Uniform API error envelope used across the whole backend.
 *
 * Shape (see docs 07-api-design.md):
 *   {
 *     "error": {
 *       "code": "VALIDATION_ERROR",
 *       "message": "human readable",
 *       "details": { ... } | null,
 *       "request_id": "uuid"
 *     }
 *   }
 */
final class ApiError
{
    public static function make(
        string $code,
        string $message,
        int $status = 400,
        mixed $details = null,
    ): JsonResponse {
        return new JsonResponse([
            'error' => [
                'code'       => $code,
                'message'    => $message,
                'details'    => $details,
                'request_id' => request()?->attributes->get('request_id') ?? (string) Str::uuid(),
            ],
        ], $status);
    }
}
