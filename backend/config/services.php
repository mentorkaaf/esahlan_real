<?php
return [
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'firebase' => [
        'server_key' => env('FIREBASE_SERVER_KEY'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials_file' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),
        'fcm_url' => 'https://fcm.googleapis.com/fcm/send',
    ],
    'waafi_pay' => [
        'api_url' => env('WAAFI_PAY_API_URL', 'https://api.waafipay.net/asm'),
        'merchant_uid' => env('WAAFI_MERCHANT_UID'),
        'api_user_id' => env('WAAFI_API_USER_ID'),
        'api_key' => env('WAAFI_API_KEY'),
        'description' => env('WAAFI_DESCRIPTION', 'eSahlan Payment'),
    ],
    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
        'places_api_key' => env('GOOGLE_PLACES_API_KEY'),
    ],
    'pusher' => [
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'cluster' => env('PUSHER_APP_CLUSTER', 'ap2'),
        'host' => env('PUSHER_HOST'),
        'port' => env('PUSHER_PORT', 443),
        'scheme' => env('PUSHER_SCHEME', 'https'),
    ],
];
