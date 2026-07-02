<?php

return [

    'paths' => ['api/*', 'broadcasting/auth', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:3000,http://localhost:5173'
    ))),

    'allowed_origins_patterns' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))),

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 3600,

    // Bearer-token API; no cookies, so credentials are not required.
    'supports_credentials' => false,

];
