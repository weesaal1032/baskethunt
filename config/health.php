<?php

return [
    'disk_threshold_percentage' => env('CALLHUB_DISK_THRESHOLD', 80),

    'notify' => [
        'email' => env('CALLHUB_HEALTH_EMAIL'),
        'slack_webhook' => env('CALLHUB_HEALTH_SLACK_WEBHOOK'),
    ],
];
