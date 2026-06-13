<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Allows Flutter Web and mobile apps to access the API.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Local development
        'http://localhost:49201',
        'http://localhost:3000',
        // Production web app
        'https://esahlan.com',
        'https://www.esahlan.com',
        'https://web.esahlan.com',
        // Hostinger hosting domains
        'https://esahlan-19f40.web.app',
        'https://esahlan-19f40.firebaseapp.com',
    ],

    'allowed_origins_patterns' => [
        // Any localhost port (development)
        '#^http://localhost:\d+$#',
        '#^http://127\.0\.0\.1:\d+$#',
        // Any esahlan subdomain over HTTPS
        '#^https://[a-z0-9\-]+\.esahlan\.com$#',
        // Firebase hosting previews
        '#^https://esahlan-19f40--[a-z0-9\-]+\.web\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Range', 'Accept-Ranges', 'Content-Length'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
