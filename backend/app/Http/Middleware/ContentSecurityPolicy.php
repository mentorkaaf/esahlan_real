<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    // Strict CSP for public/API routes (scored by Observatory)
    private const STRICT = "default-src 'self'; " .
        "script-src 'self'; " .
        "style-src 'self' 'unsafe-inline' https:; " .
        "img-src 'self' data: https: blob:; " .
        "font-src 'self' data: https:; " .
        "connect-src 'self' https: wss:; " .
        "media-src 'self' https: blob:; " .
        "object-src 'none'; " .
        "frame-ancestors 'none'; " .
        "base-uri 'self';";

    // Permissive CSP for admin panel (needs inline scripts, CDN assets)
    private const ADMIN = "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: data:; " .
        "style-src 'self' 'unsafe-inline' https: data:; " .
        "img-src 'self' data: https: blob:; " .
        "font-src 'self' data: https:; " .
        "connect-src 'self' https: wss: ws:; " .
        "media-src 'self' https: blob:; " .
        "object-src 'self'; " .
        "frame-ancestors 'self'; " .
        "base-uri 'self';";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isAdmin = $request->is('admin*') ||
                   $request->is('vendor*') ||
                   $request->is('livewire*');

        $csp = $isAdmin ? self::ADMIN : self::STRICT;

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
