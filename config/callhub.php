<?php

return [
    'domains' => [
        'auth',
        'settings',
        'providers',
        'calls',
        'recordings',
        'transcripts',
        'qa',
        'jobs',
        'installer',
        'admin',
        'audit',
        'storage',
        'health',
    ],

    'ui' => [
        'branding' => env('CALLHUB_BRANDING', 'CallHub'),
        'dark_mode_default' => env('CALLHUB_DARK_MODE', false),
    ],

    'security' => [
        'installation_locked' => env('CALLHUB_INSTALL_LOCKED', false),
        'email_otp' => [
            'enabled' => env('CALLHUB_EMAIL_OTP', false),
            'expiry_minutes' => env('CALLHUB_EMAIL_OTP_EXPIRY', 10),
        ],
    ],

    'telephony' => [
        'base_url' => env('CALLHUB_TELEPHONY_BASE_URL'),
        'auth_type' => env('CALLHUB_TELEPHONY_AUTH', 'header'),
        'pagination_size' => env('CALLHUB_TELEPHONY_PAGINATION', 100),
        'rate_limit' => env('CALLHUB_TELEPHONY_RATE_LIMIT'),
        'poll_window_days' => env('CALLHUB_TELEPHONY_POLL_WINDOW', 7),
    ],

    'storage' => [
        'default' => env('CALLHUB_STORAGE_BACKEND', 'local'),
        's3' => [
            'access_key' => env('AWS_ACCESS_KEY_ID'),
            'secret_key' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'prefix' => env('CALLHUB_STORAGE_PREFIX'),
        ],
    ],

    'transcription' => [
        'engine' => env('CALLHUB_TRANSCRIPTION_DRIVER', 'whisper_api'),
        'api_key' => env('WHISPER_API_KEY'),
        'cli_path' => env('WHISPER_CLI_BINARY'),
        'language' => env('CALLHUB_TRANSCRIPTION_LANGUAGE', 'en'),
        'max_concurrent' => env('CALLHUB_TRANSCRIPTION_MAX_CONCURRENT', 2),
    ],

    'notifications' => [
        'mail' => [
            'host' => env('MAIL_HOST'),
            'port' => env('MAIL_PORT'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'encryption' => env('MAIL_ENCRYPTION'),
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
        ],
        'slack' => [
            'webhook' => env('SLACK_WEBHOOK_URL'),
        ],
    ],

    'privacy' => [
        'pii_masking' => env('CALLHUB_PRIVACY_PII_MASKING', true),
        'retention_months' => env('CALLHUB_RETENTION_MONTHS', 12),
    ],
];
