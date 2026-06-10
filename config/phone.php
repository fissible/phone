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

    'sms' => [
        'allow_unknown_recipients' => env('PHONE_SMS_ALLOW_UNKNOWN_RECIPIENTS', false),
        'opt_out' => [
            'enabled' => env('PHONE_SMS_OPT_OUT_ENABLED', true),
            'stop_keywords' => ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'],
            'start_keywords' => ['START', 'YES', 'UNSTOP'],
        ],
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

    'numbers' => [
        'create_unknown_inbound' => true,
        'default_scope_key' => env('PHONE_DEFAULT_SCOPE_KEY', 'global'),
        'default_scope_type' => env('PHONE_DEFAULT_SCOPE_TYPE'),
        'default_scope_id' => env('PHONE_DEFAULT_SCOPE_ID'),
    ],

    'webhooks' => [
        'base_url' => env('PHONE_WEBHOOK_BASE_URL'),
        'middleware' => ['phone.twilio'],
        'store_raw_payloads' => true,
        'store_invalid_payloads' => false,
        'redact' => [],
        'replay_enabled' => true,
    ],

    'retention' => [
        'webhook_receipts_days' => 90,
        'raw_payload_days' => 30,
    ],
];
