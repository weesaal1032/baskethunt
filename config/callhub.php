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
        'login' => [
            'max_attempts' => env('CALLHUB_LOGIN_ATTEMPTS', 5),
            'decay_seconds' => env('CALLHUB_LOGIN_DECAY', 60),
        ],
        'email_otp' => [
            'enabled' => env('CALLHUB_EMAIL_OTP', false),
            'expiry_minutes' => env('CALLHUB_EMAIL_OTP_EXPIRY', 10),
        ],
    ],

    'telephony' => [
        'base_url' => env('CALLHUB_TELEPHONY_BASE_URL'),
        'auth_type' => env('CALLHUB_TELEPHONY_AUTH', 'header'),
        'api_key' => env('CALLHUB_TELEPHONY_API_KEY'),
        'pagination_size' => env('CALLHUB_TELEPHONY_PAGINATION', 100),
        'rate_limit' => env('CALLHUB_TELEPHONY_RATE_LIMIT'),
        'poll_window_days' => env('CALLHUB_TELEPHONY_POLL_WINDOW', 7),
        'headers' => [],
        'query' => [],
        'mapping' => [],
        'calls_endpoint' => '/calls',
        'recording_endpoint' => '/calls/{callId}/recording',
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
        'alert' => [
            'local_percent' => env('CALLHUB_STORAGE_ALERT_PERCENT', 80),
            's3_gb' => env('CALLHUB_STORAGE_ALERT_S3_GB', 0),
        ],
    ],

    'media' => [
        'ffmpeg_binary' => env('FFMPEG_BINARY', 'ffmpeg'),
    ],

    'transcription' => [
        'engine' => env('CALLHUB_TRANSCRIPTION_DRIVER', 'whisper_api'),
        'api_key' => env('WHISPER_API_KEY'),
        'cli_path' => env('WHISPER_CLI_BINARY'),
        'language' => env('CALLHUB_TRANSCRIPTION_LANGUAGE', 'en'),
        'max_concurrent' => env('CALLHUB_TRANSCRIPTION_MAX_CONCURRENT', 2),
        'api_timeout' => env('WHISPER_API_TIMEOUT', 30),
        'cli_model' => env('WHISPER_CLI_MODEL', 'base.en'),
        'cli_threads' => env('WHISPER_CLI_THREADS', 4),
        'cli_timeout' => env('WHISPER_CLI_TIMEOUT', 600),
        'daily_limit_minutes' => env('CALLHUB_TRANSCRIPTION_DAILY_LIMIT', 0),
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
            'recipients' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('CALLHUB_ALERT_RECIPIENTS', ''))
            ))),
        ],
        'slack' => [
            'webhook' => env('SLACK_WEBHOOK_URL'),
        ],
        'transcription' => [
            'backlog_threshold' => env('CALLHUB_TRANSCRIPTION_BACKLOG_THRESHOLD', 20),
        ],
    ],

    'qa' => [
        'rubric_version' => env('CALLHUB_QA_RUBRIC_VERSION', 1),
        'pass_threshold' => env('CALLHUB_QA_PASS_THRESHOLD', 80),
        'rubric' => [
            [
                'id' => 'cat_greeting',
                'name' => 'Greeting & Verification',
                'weight' => 30,
                'questions' => [
                    [
                        'id' => 'q_greeting_open',
                        'prompt' => 'Opened the call with brand greeting',
                        'type' => 'yes_no',
                        'weight' => 10,
                    ],
                    [
                        'id' => 'q_greeting_verification',
                        'prompt' => 'Verified caller identity against account data',
                        'type' => 'yes_no',
                        'weight' => 10,
                    ],
                    [
                        'id' => 'q_greeting_tone',
                        'prompt' => 'Tone and rapport during introduction',
                        'type' => 'scale',
                        'weight' => 10,
                        'scale_min' => 0,
                        'scale_max' => 5,
                    ],
                ],
            ],
            [
                'id' => 'cat_resolution',
                'name' => 'Resolution & Compliance',
                'weight' => 40,
                'questions' => [
                    [
                        'id' => 'q_resolution_needs',
                        'prompt' => 'Identified customer need correctly',
                        'type' => 'yes_no',
                        'weight' => 10,
                    ],
                    [
                        'id' => 'q_resolution_process',
                        'prompt' => 'Followed mandatory process checklist',
                        'type' => 'yes_no',
                        'weight' => 15,
                    ],
                    [
                        'id' => 'q_resolution_accuracy',
                        'prompt' => 'Provided accurate information or solution',
                        'type' => 'scale',
                        'weight' => 15,
                        'scale_min' => 0,
                        'scale_max' => 5,
                    ],
                ],
            ],
            [
                'id' => 'cat_closure',
                'name' => 'Closure & Next Steps',
                'weight' => 30,
                'questions' => [
                    [
                        'id' => 'q_closure_summary',
                        'prompt' => 'Summarized resolution and confirmed satisfaction',
                        'type' => 'yes_no',
                        'weight' => 10,
                    ],
                    [
                        'id' => 'q_closure_next_steps',
                        'prompt' => 'Set expectations / next steps',
                        'type' => 'scale',
                        'weight' => 10,
                        'scale_min' => 0,
                        'scale_max' => 5,
                    ],
                    [
                        'id' => 'q_closure_compliance',
                        'prompt' => 'Met compliance closing requirements',
                        'type' => 'yes_no',
                        'weight' => 10,
                    ],
                ],
            ],
        ],
    ],

    'privacy' => [
        'pii_masking' => env('CALLHUB_PRIVACY_PII_MASKING', true),
        'retention_months' => env('CALLHUB_RETENTION_MONTHS', 12),
        'deletion_grace_days' => env('CALLHUB_DELETION_GRACE_DAYS', 14),
    ],
];
