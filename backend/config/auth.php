<?php

return [

    'defaults' => [
        'guard'     => 'web',   // web guard for admin session auth (supports attempt())
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],
        // 'sanctum' guard is registered automatically by Sanctum — do NOT add here
        'global_users' => [
            'driver'   => 'sanctum',
            'provider' => 'global_users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\User::class,
        ],
        'global_users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Global\GlobalUser::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
