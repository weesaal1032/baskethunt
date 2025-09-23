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
    ],
];
