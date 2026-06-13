<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Hostinger Deployment - public_html/index.php
|--------------------------------------------------------------------------
| This file lives in public_html/ on Hostinger.
| The rest of the Laravel project lives in esahlan_backend/ (one level up).
|
| Server path example:
|   /home/u123456789/public_html/index.php      ← this file
|   /home/u123456789/esahlan_backend/vendor/...  ← Laravel root
|
| Adjust __DIR__.'/../esahlan_backend/...' if you named the folder differently.
*/

// If the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../esahlan_backend/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../esahlan_backend/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../esahlan_backend/bootstrap/app.php')
    ->handleRequest(Request::capture());
