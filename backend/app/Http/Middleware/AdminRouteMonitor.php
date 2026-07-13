<?php

namespace App\Http\Middleware;

use App\Services\SecurityAuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AdminRouteMonitor
{
    // Max 60 requests/min per IP to /admin/* (unauthenticated)
    const PROBE_LIMIT        = 60;
    const PROBE_DECAY        = 60;   // seconds
    const PROBE_BLOCK_AFTER  = 20;   // requests before flagging as scan

    public function handle(Request $request, Closure $next): mixed
    {
        $ip      = $request->ip();
        $path    = $request->path();
        $isLogin = $request->routeIs('admin.login') || $request->routeIs('admin.login.post');

        // Skip the login page itself — AdminAuthController handles that
        if ($isLogin) {
            return $next($request);
        }

        // If not authenticated, this is an unauthorized probe of admin routes
        if (!Auth::check()) {
            $key = 'admin_probe:' . $ip;
            RateLimiter::hit($key, self::PROBE_DECAY);
            $count = RateLimiter::attempts($key);

            $severity = match (true) {
                $count >= 40 => 'critical',
                $count >= 20 => 'warn',
                default      => 'info',
            };

            // Only log every 5th probe to avoid flooding the table
            if ($count === 1 || $count % 5 === 0) {
                SecurityAuditService::log('admin.route.probe', $severity, array_merge(
                    SecurityAuditService::fromRequest($request),
                    ['path' => $path, 'method' => $request->method(), 'probe_count' => $count]
                ));
            }

            // Hard block after 100 probes in 60s — return 403 (don't redirect to login, don't help them)
            if ($count > 100) {
                SecurityAuditService::log('admin.route.blocked', 'critical', array_merge(
                    SecurityAuditService::fromRequest($request),
                    ['path' => $path, 'probe_count' => $count, 'reason' => 'scan_detected']
                ));
                abort(403, 'Forbidden');
            }

            // Normal unauthenticated: let Laravel auth middleware redirect to login
            return $next($request);
        }

        // Authenticated — check role. If wrong role slipped through, log it.
        $user       = Auth::user();
        $adminRoles = ['super_admin', 'admin', 'operations_manager', 'finance_manager',
                       'marketing_manager', 'customer_support', 'employee'];

        if (!in_array($user->role?->slug, $adminRoles)) {
            SecurityAuditService::log('admin.privilege.escalation', 'critical', array_merge(
                SecurityAuditService::fromRequest($request),
                ['user_id' => $user->id, 'identifier' => $user->email,
                 'role' => $user->role?->slug, 'path' => $path]
            ));
            abort(403, 'Insufficient privileges');
        }

        return $next($request);
    }
}
