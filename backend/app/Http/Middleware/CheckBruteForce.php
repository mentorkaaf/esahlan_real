<?php

namespace App\Http\Middleware;

use App\Models\LoginAttempt;
use App\Services\SecurityAuditService;
use Closure;
use Illuminate\Http\Request;

class CheckBruteForce
{
    // After MAX_FAILURES within WINDOW_MINUTES, block for LOCKOUT_MINUTES
    const MAX_FAILURES     = 5;
    const WINDOW_MINUTES   = 15;
    const LOCKOUT_MINUTES  = 30;

    public function handle(Request $request, Closure $next): mixed
    {
        $identifier = trim((string) ($request->input('phone') ?? $request->input('email') ?? ''));
        $ip         = $request->ip();
        $since      = now()->subMinutes(self::LOCKOUT_MINUTES);

        $ipFailures = LoginAttempt::where('ip_address', $ip)
            ->where('succeeded', false)
            ->where('attempted_at', '>=', $since)
            ->count();

        $idFailures = $identifier
            ? LoginAttempt::where('identifier', $identifier)
                ->where('succeeded', false)
                ->where('attempted_at', '>=', $since)
                ->count()
            : 0;

        if ($ipFailures >= self::MAX_FAILURES || $idFailures >= self::MAX_FAILURES) {
            SecurityAuditService::log('login.blocked', 'crit', array_merge(
                SecurityAuditService::fromRequest($request),
                ['identifier' => $identifier, 'ip_failures' => $ipFailures, 'id_failures' => $idFailures]
            ));

            return response()->json([
                'success' => false,
                'message' => 'Too many failed login attempts. Try again in ' . self::LOCKOUT_MINUTES . ' minutes.',
            ], 429);
        }

        return $next($request);
    }
}
