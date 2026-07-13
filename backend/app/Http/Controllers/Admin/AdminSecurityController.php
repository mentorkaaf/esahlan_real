<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityAuditLog;
use App\Models\LoginAttempt;
use Illuminate\Support\Facades\DB;

class AdminSecurityController extends Controller
{
    public function soc()
    {
        $now = now();

        // Auth failures last hour
        $authFailures = SecurityAuditLog::where('event', 'login.failed')
            ->where('created_at', '>=', $now->copy()->subHour())
            ->count();

        // Blocked IPs last 24h
        $blockedIps = SecurityAuditLog::where('event', 'login.blocked')
            ->where('created_at', '>=', $now->copy()->subDay())
            ->distinct('ip_address')
            ->count();

        // Auth success rate last hour
        $totalAuth = SecurityAuditLog::whereIn('event', ['login.failed', 'login.success'])
            ->where('created_at', '>=', $now->copy()->subHour())
            ->count();
        $successAuth = SecurityAuditLog::where('event', 'login.success')
            ->where('created_at', '>=', $now->copy()->subHour())
            ->count();
        $successRate = $totalAuth > 0 ? round(($successAuth / $totalAuth) * 100, 1) : 100;

        // Active sessions (users with token updated in last 30 min)
        try {
            $activeSessions = DB::table('personal_access_tokens')
                ->where('last_used_at', '>=', $now->copy()->subMinutes(30))
                ->count();
        } catch (\Throwable) {
            $activeSessions = 0;
        }

        // Hourly auth failures last 24h
        $hourlyFailures = SecurityAuditLog::where('event', 'login.failed')
            ->where('created_at', '>=', $now->copy()->subDay())
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour')
            ->map(fn ($r) => $r->count);

        // Fill all 24 hours
        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyData[] = ['hour' => $h, 'count' => $hourlyFailures->get($h, 0)];
        }

        // Event breakdown last 24h
        $eventBreakdown = SecurityAuditLog::where('created_at', '>=', $now->copy()->subDay())
            ->selectRaw('event, COUNT(*) as count')
            ->groupBy('event')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Top attacker IPs last 24h
        $topIps = SecurityAuditLog::where('event', 'login.failed')
            ->where('created_at', '>=', $now->copy()->subDay())
            ->selectRaw('ip_address, COUNT(*) as attempts')
            ->groupBy('ip_address')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get();

        // Recent critical events
        $criticalEvents = SecurityAuditLog::where('severity', 'critical')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Recent audit log (last 50)
        $auditLog = SecurityAuditLog::orderByDesc('created_at')
            ->limit(50)
            ->get();

        // Severity counts last 24h
        $severityCounts = SecurityAuditLog::where('created_at', '>=', $now->copy()->subDay())
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->get()
            ->keyBy('severity');

        return view('admin.security.soc', compact(
            'authFailures', 'blockedIps', 'successRate', 'activeSessions',
            'hourlyData', 'eventBreakdown', 'topIps',
            'criticalEvents', 'auditLog', 'severityCounts'
        ));
    }
}
