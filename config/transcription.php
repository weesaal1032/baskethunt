<?php

return [
    'default' => env('CALLHUB_TRANSCRIPTION_DRIVER', 'whisper_api'),

    'drivers' => [
        'whisper_api' => [
            'label' => 'Whisper API',
            'endpoint' => env('WHISPER_API_ENDPOINT'),
            'api_key' => env('WHISPER_API_KEY'),
            'timeout' => env('WHISPER_API_TIMEOUT', 30),
        ],
        'whisper_cli' => [
            'label' => 'Local whisper.cpp',
            'binary' => env('WHISPER_CLI_BINARY', '/usr/local/bin/whisper'),
            'model' => env('WHISPER_CLI_MODEL', 'base.en'),
            'threads' => env('WHISPER_CLI_THREADS', 4),
            'timeout' => env('WHISPER_CLI_TIMEOUT', 600),
        ],
        'mock' => [
            'label' => 'Mock (Diagnostics)',
            'text' => env('CALLHUB_TRANSCRIPTION_MOCK_TEXT', 'Mock transcription generated for diagnostics.'),
            'confidence' => env('CALLHUB_TRANSCRIPTION_MOCK_CONFIDENCE', 0.95),
        ],
    ],

    'options' => [
        'max_duration_seconds' => env('CALLHUB_TRANSCRIPTION_MAX_DURATION', 7200),
        'enable_diarization' => env('CALLHUB_TRANSCRIPTION_DIARIZATION', true),
        'daily_limit_minutes' => env('CALLHUB_TRANSCRIPTION_DAILY_LIMIT', 0),
    ],
];
