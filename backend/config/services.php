<?php

return [

    /*
    | Third-party integrations. Per-tenant credentials (bKash/Nagad/SMS) are
    | stored encrypted on the tenant record and override these platform defaults
    | at runtime; the keys here are only fallbacks / sandbox creds.
    */

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
    ],

    // SMS aggregators common in BD (SSL Wireless, bulk gateways).
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'base_url' => env('SMS_BASE_URL'),
        'api_key' => env('SMS_API_KEY'),
        'sender_id' => env('SMS_SENDER_ID', 'ColdChain'),
    ],

    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
        'token' => env('WHATSAPP_TOKEN'),
    ],

    // Mobile financial services (webhooks verified in PaymentWebhookController).
    'bkash' => [
        'base_url' => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh'),
        'app_key' => env('BKASH_APP_KEY'),
        'app_secret' => env('BKASH_APP_SECRET'),
        'username' => env('BKASH_USERNAME'),
        'password' => env('BKASH_PASSWORD'),
    ],

    'nagad' => [
        'base_url' => env('NAGAD_BASE_URL', 'https://api.mynagad.com'),
        'merchant_id' => env('NAGAD_MERCHANT_ID'),
        'public_key' => env('NAGAD_PUBLIC_KEY'),
        'private_key' => env('NAGAD_PRIVATE_KEY'),
    ],

    // Python AI microservice (forecasts, anomaly, optimization recommendations).
    'ai' => [
        'base_url' => env('AI_SERVICE_URL', 'http://ai:8000'),
        'token' => env('AI_SERVICE_TOKEN'),
        'timeout' => (int) env('AI_SERVICE_TIMEOUT', 10),
    ],

];
