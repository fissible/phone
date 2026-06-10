<?php

declare(strict_types=1);

return [
    'provider' => env('PHONE_PROVIDER', 'twilio'),

    'route_prefix' => env('PHONE_ROUTE_PREFIX', 'phone'),

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'default_from' => env('TWILIO_FROM'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'validate_webhooks' => env('TWILIO_VALIDATE_WEBHOOKS', true),
    ],

    'default_voice' => [
        'mode' => 'forward',
        'forward_to' => env('PHONE_FORWARD_TO'),
        'timeout' => 20,
        'after_hours_mode' => 'voicemail',
    ],

    'business_hours' => [
        'timezone' => env('PHONE_TIMEZONE', config('app.timezone', 'UTC')),
        'weekly' => [],
        'holidays' => [],
    ],

    'webhooks' => [
        'base_url' => env('PHONE_WEBHOOK_BASE_URL'),
        'middleware' => ['phone.twilio'],
        'store_raw_payloads' => true,
        'redact' => [],
        'replay_enabled' => true,
    ],

    'retention' => [
        'webhook_receipts_days' => 90,
        'raw_payload_days' => 30,
    ],
];
