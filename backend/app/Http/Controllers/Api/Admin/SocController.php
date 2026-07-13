<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SocController extends Controller
{
    public function stats(Request $request)
    {
        $since1h  = now()->subHour();
        $since24h = now()->subDay();
        $since7d  = now()->subDays(7);

        $authFailures1h = SecurityAuditLog::where('event', 'login.failed')
            ->where('created_at', '>=', $since1h)
            ->count();

        $blockedIps24h = SecurityAuditLog::where('event', 'login.blocked')
            ->where('created_at', '>=', $since24h)
            ->distinct('ip_address')
            ->count('ip_address');

        $activeThreats = SecurityAuditLog::where('severity', 'crit')
            ->where('created_at', '>=', $since1h)
            ->count();

        // Hourly auth failures for last 24h
        $hourlyFailures = SecurityAuditLog::where('event', 'login.failed')
            ->where('created_at', '>=', $since24h)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupByRaw('HOUR(created_at)')
            ->orderByRaw('HOUR(created_at)')
            ->pluck('count', 'hour');

        // Attack vector breakdown (top events last 24h)
        $eventBreakdown = SecurityAuditLog::where('created_at', '>=', $since24h)
            ->whereIn('severity', ['crit', 'warn'])
            ->selectRaw('event, COUNT(*) as count')
            ->groupBy('event')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Top attacking IPs (by failed attempts)
        $topAttackerIps = LoginAttempt::where('succeeded', false)
            ->where('attempted_at', '>=', $since24h)
            ->selectRaw('ip_address, COUNT(*) as count')
            ->groupBy('ip_address')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Recent critical events
        $recentCritical = SecurityAuditLog::whereIn('severity', ['crit', 'warn'])
            ->latest('created_at')
            ->limit(20)
            ->get(['id', 'event', 'severity', 'user_identifier', 'ip_address', 'metadata', 'created_at']);

        // Auth success rate (last 1h)
        $totalAttempts1h = LoginAttempt::where('attempted_at', '>=', $since1h)->count();
        $successAttempts1h = LoginAttempt::where('attempted_at', '>=', $since1h)->where('succeeded', true)->count();
        $authSuccessRate = $totalAttempts1h > 0
            ? round(($successAttempts1h / $totalAttempts1h) * 100, 1)
            : 100;

        // Recent audit log
        $auditLog = SecurityAuditLog::latest('created_at')
            ->limit(50)
            ->get(['id', 'event', 'severity', 'user_id', 'user_identifier', 'ip_address', 'metadata', 'created_at']);

        // New registrations 24h
        $newUsers24h = User::where('created_at', '>=', $since24h)->count();

        // Total active sessions
        $activeSessions = DB::table('personal_access_tokens')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => [
                    'auth_failures_per_hour'  => $authFailures1h,
                    'blocked_ips_24h'         => $blockedIps24h,
                    'active_threats_1h'       => $activeThreats,
                    'auth_success_rate'       => $authSuccessRate,
                    'active_sessions'         => $activeSessions,
                    'new_users_24h'           => $newUsers24h,
                ],
                'hourly_auth_failures' => $hourlyFailures,
                'event_breakdown'      => $eventBreakdown,
                'top_attacker_ips'     => $topAttackerIps,
                'recent_critical'      => $recentCritical,
                'audit_log'            => $auditLog,
            ],
        ]);
    }

    public function recentEvents(Request $request)
    {
        $severity = $request->query('severity');
        $event    = $request->query('event');
        $limit    = min((int) $request->query('limit', 50), 200);

        $q = SecurityAuditLog::latest('created_at')->limit($limit);
        if ($severity) $q->where('severity', $severity);
        if ($event)    $q->where('event', 'like', $event . '%');

        return response()->json(['success' => true, 'data' => $q->get()]);
    }
}
