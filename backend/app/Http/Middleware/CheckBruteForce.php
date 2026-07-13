<?php

namespace App\Http\Middleware;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\FcmService;
use App\Services\SecurityAuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CheckBruteForce
{
    // After MAX_FAILURES within WINDOW_MINUTES, block for LOCKOUT_MINUTES
    const MAX_FAILURES    = 5;
    const WINDOW_MINUTES  = 15;
    const LOCKOUT_MINUTES = 30;

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

            // Notify the account owner once per lockout window (not on every blocked attempt)
            if ($identifier) {
                $notifKey = 'brute_force_notified:' . md5($identifier);
                if (!Cache::has($notifKey)) {
                    Cache::put($notifKey, true, now()->addMinutes(self::LOCKOUT_MINUTES));
                    $this->notifyAccountOwner($identifier, $ip);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Too many failed login attempts. Try again in ' . self::LOCKOUT_MINUTES . ' minutes.',
            ], 429);
        }

        return $next($request);
    }

    private function notifyAccountOwner(string $identifier, string $ip): void
    {
        try {
            $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
                ? User::where('email', $identifier)->first()
                : User::where('phone', $identifier)->first();

            if (!$user || empty($user->fcm_token)) return;

            FcmService::sendToToken(
                $user->fcm_token,
                '⚠️ Security Alert',
                'Someone is repeatedly trying to access your account from ' . $ip . '. Your account is temporarily locked.',
                [
                    'type'      => 'security_alert',
                    'alert'     => 'brute_force_detected',
                    'ip'        => $ip,
                    'deep_link' => '/profile/security',
                ]
            );
        } catch (\Throwable $e) {
            // Never let notification failure block the response
        }
    }
}
