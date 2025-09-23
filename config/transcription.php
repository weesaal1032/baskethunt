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
        ],
    ],

    'options' => [
        'max_duration_seconds' => env('CALLHUB_TRANSCRIPTION_MAX_DURATION', 7200),
        'enable_diarization' => env('CALLHUB_TRANSCRIPTION_DIARIZATION', true),
    ],
];
