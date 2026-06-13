<?php
/**
 * PHP built-in server router with CORS headers for ALL responses.
 * Run from backend/ directory:
 *   php -S 127.0.0.1:8000 router.php
 */

// CORS headers for every response
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept, Origin');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri     = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$pubDir  = __DIR__ . '/public';
$file    = $pubDir . $uri;

// Serve static files from public/ manually (so CORS headers are included)
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = [
        'jpg'  => 'image/jpeg',  'jpeg' => 'image/jpeg',
        'png'  => 'image/png',   'gif'  => 'image/gif',
        'webp' => 'image/webp',  'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon','css'  => 'text/css',
        'js'   => 'application/javascript',
        'woff' => 'font/woff',   'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',    'eot'  => 'application/vnd.ms-fontobject',
        'json' => 'application/json',
        'txt'  => 'text/plain',  'html' => 'text/html',
    ][$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

// All other requests → Laravel
chdir($pubDir);
require $pubDir . '/index.php';
