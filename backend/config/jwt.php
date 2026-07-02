<?php

/*
 * Configuration for php-open-source-saver/jwt-auth (maintained fork of tymon/jwt-auth).
 * Access tokens are short-lived (15 min); refresh handled by rotating opaque
 * refresh tokens stored server-side (see App\Models\RefreshToken).
 */

return [

    'secret' => env('JWT_SECRET'),

    'keys' => [
        'public' => env('JWT_PUBLIC_KEY'),
        'private' => env('JWT_PRIVATE_KEY'),
        'passphrase' => env('JWT_PASSPHRASE'),
    ],

    // Access-token lifetime in minutes.
    'ttl' => (int) env('JWT_TTL', 15),

    // Refresh window in minutes (14 days) — how long a token may be refreshed.
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 20160),

    'algo' => env('JWT_ALGO', 'HS256'),

    'required_claims' => [
        'iss', 'iat', 'exp', 'nbf', 'sub', 'jti',
    ],

    'persistent_claims' => [
        'tenant_id', 'roles', 'branch_ids',
    ],

    'lock_subject' => true,

    'leeway' => (int) env('JWT_LEEWAY', 0),

    'blacklist_enabled' => (bool) env('JWT_BLACKLIST_ENABLED', true),

    'blacklist_grace_period' => (int) env('JWT_BLACKLIST_GRACE_PERIOD', 30),

    'show_black_list_exception' => (bool) env('JWT_SHOW_BLACKLIST_EXCEPTION', true),

    'decrypt_cookies' => false,

    'providers' => [
        'jwt' => PHPOpenSourceSaver\JWTAuth\Providers\JWT\Lcobucci::class,
        'auth' => PHPOpenSourceSaver\JWTAuth\Providers\Auth\Illuminate::class,
        'storage' => PHPOpenSourceSaver\JWTAuth\Providers\Storage\Illuminate::class,
    ],

];
