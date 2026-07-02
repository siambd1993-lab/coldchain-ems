<?php

return [

    'name' => env('APP_NAME', 'ColdChain EMS'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    /*
    | Persist and compute everything in UTC; the display timezone is applied at
    | the presentation layer (API resources / clients) — see 'display_timezone'.
    */
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ColdChain custom application settings
    |--------------------------------------------------------------------------
    | Regional defaults for a Bangladesh-first, bilingual product. Money is
    | stored in minor units (poisha, 1 BDT = 100 poisha) as BIGINT everywhere.
    */
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Dhaka'),
    'currency' => env('APP_CURRENCY', 'BDT'),
    'currency_minor_units' => 100,

];
