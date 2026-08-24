<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remove headers that disclose server internals.
 * Applies to every response — never reveal stack or runtime version.
 */
class SanitizeResponseHeaders
{
    private const REMOVE = [
        'X-Powered-By',       // PHP version
        'X-Generator',        // CMS fingerprint
        'X-Runtime',          // Rails-style timing (sometimes set by proxies)
        'X-Debug-Token',      // Symfony debug toolbar
        'X-Debug-Token-Link', // Symfony debug toolbar
        'Server',             // Nginx hides version via server_tokens off; belt-and-suspenders here
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::REMOVE as $header) {
            $response->headers->remove($header);
        }

        return $response;
    }
}
