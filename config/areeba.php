<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Gateway
    |--------------------------------------------------------------------------
    |
    | Areeba runs on the Mastercard Gateway. Test and live traffic share the same
    | host: a test merchant ID (usually prefixed "TEST") moves no real money.
    | Create the API password in Merchant Administration under
    | Admin > Integration Settings.
    |
    */
    'gateway_url' => env('AREEBA_GATEWAY_URL', 'https://epayment.areeba.com'),
    'api_version' => env('AREEBA_API_VERSION', 100),
    'merchant_id' => env('AREEBA_MERCHANT_ID'),
    'api_password' => env('AREEBA_API_PASSWORD'),

    // Shown to the payer on the hosted payment page.
    'merchant_name' => env('AREEBA_MERCHANT_NAME', env('APP_NAME', 'Laravel')),

    // Used when a call does not pass a currency (ISO 4217, e.g. USD, LBP).
    'currency' => env('AREEBA_CURRENCY', 'USD'),

    // Seconds. Reads (retrieve order/token) are retried on connection and 5xx
    // errors; writes are never retried because they could move money twice.
    'timeout' => env('AREEBA_TIMEOUT', 30),
    'connect_timeout' => env('AREEBA_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Webhook notifications
    |--------------------------------------------------------------------------
    |
    | Enable notifications in Merchant Administration and copy its notification
    | secret to AREEBA_WEBHOOK_SECRET; requests without it are rejected. When the
    | webhook URL is https, checkout() sends it as order.notificationUrl, so no
    | URL needs configuring on the gateway side.
    |
    */
    'webhook' => [
        'enabled' => env('AREEBA_WEBHOOK_ENABLED', true),
        'secret' => env('AREEBA_WEBHOOK_SECRET'),
        'path' => env('AREEBA_WEBHOOK_PATH', 'areeba/webhook'),

        // The route is deliberately outside the `web` group: the gateway sends
        // no session or CSRF token. Authenticity comes from the secret.
        'middleware' => [],
    ],
];
