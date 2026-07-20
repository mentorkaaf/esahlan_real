<?php
return [
    'api_key'    => env('LIVEKIT_API_KEY', ''),
    'api_secret' => env('LIVEKIT_API_SECRET', ''),
    // wss:// for production, ws:// for local dev
    'host'       => env('LIVEKIT_HOST', 'wss://livekit.esahlan.com'),
];
