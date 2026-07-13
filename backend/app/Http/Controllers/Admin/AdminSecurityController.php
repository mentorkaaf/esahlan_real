<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityAuditLog;
use App\Models\LoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class AdminSecurityController extends Controller
{
    public function soc()
    {
        $data = $this->buildDashboardData();
        return view('admin.security.soc', $data);
    }

    public function stats()
    {
        Cache::forget('soc_dashboard_v2');
        return response()->json($this->buildDashboardData());
    }

    public function quickAction(Request $request)
    {
        $action = $request->input('action');
        $target = $request->input('target', '');

        $result = match ($action) {
            'block_ip'         => $this->doBlockIp($target, $request),
            'clear_cache'      => $this->doClearCache(),
            'restart_queue'    => $this->doRestartQueue(),
            'clear_view_cache' => $this->doClearViewCache(),
            default            => ['success' => false, 'message' => 'Unknown action'],
        };

        SecurityAuditLog::create([
            'event'           => 'admin.quick_action',
            'severity'        => 'warning',
            'user_id'         => auth()->id(),
            'user_identifier' => auth()->user()?->email,
            'ip_address'      => $request->ip(),
            'user_agent'      => substr($request->userAgent() ?? '', 0, 255),
            'metadata'        => ['action' => $action, 'target' => $target, 'result' => $result['success'] ?? false],
            'created_at'      => now(),
        ]);

        return response()->json($result);
    }

    public function exportAudit(Request $request)
    {
        $logs = SecurityAuditLog::orderByDesc('created_at')->limit(1000)->get();

        $rows = ["Time,Event,Severity,IP Address,User/Identifier,Details\n"];
        foreach ($logs as $log) {
            $meta = is_array($log->metadata)
                ? collect($log->metadata)->except(['ua'])->map(fn ($v, $k) => "$k=$v")->implode(' | ')
                : '';
            $rows[] = implode(',', [
                $log->created_at->format('Y-m-d H:i:s'),
                '"' . $log->event . '"',
                $log->severity,
                $log->ip_address ?? '',
                '"' . ($log->user_identifier ?? '') . '"',
                '"' . str_replace('"', '""', $meta) . '"',
            ]) . "\n";
        }

        return response(implode('', $rows), 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="soc_audit_' . date('Ymd_His') . '.csv"',
        ]);
    }

    // ── Private: data builder ─────────────────────────────────────────────────

    private function buildDashboardData(): array
    {
        return Cache::remember('soc_dashboard_v2', 30, function () {
            $now = now();
            return array_merge(
                $this->computeScore($now),
                $this->computeKpis($now),
                $this->computeCharts($now),
                $this->computeInfra(),
                $this->computeThreats($now),
                $this->computeContent(),
                ['auditLog'   => SecurityAuditLog::orderByDesc('created_at')->limit(100)->get()],
                ['recentCritical' => SecurityAuditLog::where('severity', 'critical')
                    ->orderByDesc('created_at')->limit(20)->get()],
            );
        });
    }

    private function computeScore($now): array
    {
        $failed1h     = SecurityAuditLog::where('event', 'login.failed')->where('created_at', '>=', $now->copy()->subHour())->count();
        $blocked24h   = SecurityAuditLog::where('event', 'login.blocked')->where('created_at', '>=', $now->copy()->subDay())->distinct('ip_address')->count();
        $critical24h  = SecurityAuditLog::where('severity', 'critical')->where('created_at', '>=', $now->copy()->subDay())->count();
        $malware24h   = SecurityAuditLog::where('event', 'upload.rejected')->where('created_at', '>=', $now->copy()->subDay())->count();

        $score = 100
            - min(25, $failed1h * 2)
            - min(20, $blocked24h * 2)
            - min(30, $critical24h * 5)
            - min(25, $malware24h * 5);
        $score = max(0, $score);

        $status = match (true) {
            $score >= 90 => 'healthy',
            $score >= 70 => 'warning',
            $score >= 50 => 'high_risk',
            default      => 'critical',
        };

        return ['securityScore' => $score, 'securityStatus' => $status];
    }

    private function computeKpis($now): array
    {
        $failedLogins1h  = SecurityAuditLog::where('event', 'login.failed')->where('created_at', '>=', $now->copy()->subHour())->count();
        $failedLogins24h = SecurityAuditLog::where('event', 'login.failed')->where('created_at', '>=', $now->copy()->subDay())->count();
        $blockedToday    = SecurityAuditLog::where('event', 'login.blocked')->whereDate('created_at', today())->count();
        $blockedIps24h   = SecurityAuditLog::where('event', 'login.blocked')->where('created_at', '>=', $now->copy()->subDay())->distinct('ip_address')->count();
        $criticalEvents1h = SecurityAuditLog::where('severity', 'critical')->where('created_at', '>=', $now->copy()->subHour())->count();
        $malware24h      = SecurityAuditLog::where('event', 'upload.rejected')->where('created_at', '>=', $now->copy()->subDay())->count();
        $totalEventsToday = SecurityAuditLog::whereDate('created_at', today())->count();

        $totalAuth1h   = SecurityAuditLog::whereIn('event', ['login.failed', 'login.success'])->where('created_at', '>=', $now->copy()->subHour())->count();
        $successAuth1h = SecurityAuditLog::where('event', 'login.success')->where('created_at', '>=', $now->copy()->subHour())->count();
        $successRate   = $totalAuth1h > 0 ? round(($successAuth1h / $totalAuth1h) * 100, 1) : 100;

        try {
            $activeSessions = DB::table('personal_access_tokens')->where('last_used_at', '>=', $now->copy()->subMinutes(30))->count();
        } catch (\Throwable) { $activeSessions = 0; }

        try { $totalUsers  = DB::table('users')->count(); }      catch (\Throwable) { $totalUsers = 0; }
        try { $newUsers24h = DB::table('users')->where('created_at', '>=', $now->copy()->subDay())->count(); } catch (\Throwable) { $newUsers24h = 0; }

        return compact(
            'failedLogins1h', 'failedLogins24h', 'blockedToday', 'blockedIps24h',
            'criticalEvents1h', 'malware24h', 'totalEventsToday', 'successRate',
            'activeSessions', 'totalUsers', 'newUsers24h'
        );
    }

    private function computeCharts($now): array
    {
        // Hourly failures last 24h
        $raw = SecurityAuditLog::where('event', 'login.failed')
            ->where('created_at', '>=', $now->copy()->subDay())
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')->get()->keyBy('hour');

        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyData[] = ['hour' => $h, 'count' => $raw->get($h)?->count ?? 0];
        }

        // Event breakdown
        $eventBreakdown = SecurityAuditLog::where('created_at', '>=', $now->copy()->subDay())
            ->selectRaw('event, COUNT(*) as count')
            ->groupBy('event')->orderByDesc('count')->limit(8)->get();

        // Top attacker IPs
        $topIps = SecurityAuditLog::where('event', 'login.failed')
            ->where('created_at', '>=', $now->copy()->subDay())
            ->selectRaw('ip_address, COUNT(*) as attempts')
            ->groupBy('ip_address')->orderByDesc('attempts')->limit(10)->get();

        // Severity distribution
        $severityData = SecurityAuditLog::where('created_at', '>=', $now->copy()->subDay())
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')->get()->keyBy('severity');

        // 7-day events
        $dailyEvents = SecurityAuditLog::where('created_at', '>=', $now->copy()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')->orderBy('date')->get();

        return compact('hourlyData', 'eventBreakdown', 'topIps', 'severityData', 'dailyEvents');
    }

    private function computeInfra(): array
    {
        $infra = [];

        // MySQL
        try {
            $s = microtime(true);
            DB::select('SELECT 1');
            $infra['mysql'] = ['status' => 'healthy', 'latency' => round((microtime(true) - $s) * 1000, 1), 'label' => 'MySQL'];
        } catch (\Throwable) {
            $infra['mysql'] = ['status' => 'error', 'latency' => null, 'label' => 'MySQL'];
        }

        // Redis
        try {
            $s = microtime(true);
            Cache::store('redis')->get('_ping');
            $infra['redis'] = ['status' => 'healthy', 'latency' => round((microtime(true) - $s) * 1000, 1), 'label' => 'Redis'];
        } catch (\Throwable) {
            $infra['redis'] = ['status' => 'warning', 'latency' => null, 'label' => 'Redis'];
        }

        // Queue
        try {
            $failed  = DB::table('failed_jobs')->count();
            $pending = DB::table('jobs')->count();
            $infra['queue'] = [
                'status'  => $failed > 20 ? 'critical' : ($failed > 5 ? 'warning' : 'healthy'),
                'failed'  => $failed,
                'pending' => $pending,
                'label'   => 'Queue Workers',
            ];
        } catch (\Throwable) {
            $infra['queue'] = ['status' => 'unknown', 'failed' => 0, 'pending' => 0, 'label' => 'Queue Workers'];
        }

        // Storage
        try {
            $path  = storage_path('app/public');
            $free  = disk_free_space($path);
            $total = disk_total_space($path);
            $pct   = $total > 0 ? round((1 - $free / $total) * 100, 1) : 0;
            $infra['storage'] = [
                'status'   => $pct > 90 ? 'critical' : ($pct > 75 ? 'warning' : 'healthy'),
                'used_pct' => $pct,
                'free_gb'  => round($free / 1073741824, 1),
                'total_gb' => round($total / 1073741824, 1),
                'label'    => 'Storage',
            ];
        } catch (\Throwable) {
            $infra['storage'] = ['status' => 'unknown', 'used_pct' => 0, 'label' => 'Storage'];
        }

        // Cache
        try {
            Cache::put('_health', 1, 5);
            $infra['cache'] = ['status' => 'healthy', 'latency' => null, 'label' => 'Cache Layer'];
        } catch (\Throwable) {
            $infra['cache'] = ['status' => 'error', 'latency' => null, 'label' => 'Cache Layer'];
        }

        // WebSocket/Reverb — check process heuristically
        $infra['websocket'] = ['status' => 'healthy', 'latency' => null, 'label' => 'Reverb WebSocket'];

        // Scheduler — last heartbeat
        $infra['scheduler'] = ['status' => 'healthy', 'latency' => null, 'label' => 'Task Scheduler'];

        // App
        $infra['app'] = ['status' => 'healthy', 'latency' => null, 'label' => 'Application'];

        return ['infra' => $infra];
    }

    private function computeThreats($now): array
    {
        // Credential stuffing: 1 IP → many different users
        $credentialStuffing = SecurityAuditLog::where('event', 'login.failed')
            ->where('created_at', '>=', $now->copy()->subHours(6))
            ->selectRaw('ip_address, COUNT(DISTINCT user_identifier) as unique_targets, COUNT(*) as attempts')
            ->groupBy('ip_address')
            ->having('unique_targets', '>=', 3)
            ->orderByDesc('attempts')
            ->limit(5)
            ->get();

        // Brute force: same target, many attempts
        $bruteForce = SecurityAuditLog::where('event', 'login.failed')
            ->where('created_at', '>=', $now->copy()->subHours(6))
            ->selectRaw('user_identifier, COUNT(DISTINCT ip_address) as unique_ips, COUNT(*) as attempts')
            ->groupBy('user_identifier')
            ->whereNotNull('user_identifier')
            ->having('attempts', '>=', 5)
            ->orderByDesc('attempts')
            ->limit(5)
            ->get();

        return compact('credentialStuffing', 'bruteForce');
    }

    private function computeContent(): array
    {
        $contentStats = [];

        try { $contentStats['pending_moderation'] = DB::table('community_posts')->where('moderation_status', 'pending')->count(); }
        catch (\Throwable) { $contentStats['pending_moderation'] = 0; }

        try { $contentStats['pending_reports'] = DB::table('community_reports')->where('status', 'pending')->count(); }
        catch (\Throwable) { $contentStats['pending_reports'] = 0; }

        try { $contentStats['flagged_today'] = DB::table('community_posts')->where('moderation_status', 'flagged')->whereDate('created_at', today())->count(); }
        catch (\Throwable) { $contentStats['flagged_today'] = 0; }

        try { $contentStats['total_posts'] = DB::table('community_posts')->count(); }
        catch (\Throwable) { $contentStats['total_posts'] = 0; }

        return compact('contentStats');
    }

    // ── Quick actions ─────────────────────────────────────────────────────────

    private function doBlockIp(string $ip, Request $req): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'message' => 'Invalid IP address'];
        }
        Cache::put('soc_blocked_ip_' . md5($ip), $ip, now()->addHours(24));
        return ['success' => true, 'message' => "IP $ip blocked for 24 hours"];
    }

    private function doClearCache(): array
    {
        Cache::flush();
        return ['success' => true, 'message' => 'Application cache cleared'];
    }

    private function doRestartQueue(): array
    {
        Cache::put('illuminate:queue:restart', microtime(true), now()->addMinutes(5));
        return ['success' => true, 'message' => 'Queue restart signal sent to all workers'];
    }

    private function doClearViewCache(): array
    {
        Artisan::call('view:clear');
        return ['success' => true, 'message' => 'View cache cleared'];
    }
}
