<?php

return [
    'default' => env('CALLHUB_STORAGE_BACKEND', 'local'),

    'backends' => [
        'local' => [
            'driver' => 'local',
            'label' => 'Local Disk',
            'config' => [],
        ],
        's3' => [
            'driver' => 's3',
            'label' => 'S3 Compatible',
            'config' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION'),
                'bucket' => env('AWS_BUCKET'),
                'endpoint' => env('AWS_ENDPOINT'),
            ],
        ],
    ],
];
