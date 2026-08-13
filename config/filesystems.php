<?php
return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'cloud'   => env('FILESYSTEM_CLOUD', 's3'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'throw'  => false,
        ],
        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw'      => false,
        ],
    ],
];
