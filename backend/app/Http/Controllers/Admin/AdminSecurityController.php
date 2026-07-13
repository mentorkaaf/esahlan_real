<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class AdminSecurityController extends Controller
{
    // ── Public entry points ───────────────────────────────────────────────────

    public function soc()
    {
        return view('admin.security.soc', $this->fullPayload());
    }

    public function stats()
    {
        Cache::forget('soc_v3_main');
        Cache::forget('soc_attacker_geo'); // always refresh geo on poll
        return response()->json($this->fullPayload());
    }

    public function systemMetrics()
    {
        return response()->json($this->sysMetrics());
    }

    public function liveEvents()
    {
        $since = request('since');
        $q = SecurityAuditLog::orderByDesc('created_at')->limit(25);
        if ($since) {
            $q->where('created_at', '>', $since);
        }
        return response()->json([
            'events'    => $q->get(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function quickAction(Request $request)
    {
        $action = $request->input('action', '');
        $target = $request->input('target', '');

        $result = match ($action) {
            'block_ip'         => $this->doBlockIp($target),
            'clear_cache'      => $this->doClearCache(),
            'restart_queue'    => $this->doRestartQueue(),
            'clear_view_cache' => $this->doClearViewCache(),
            'clear_all_cache'  => $this->doClearAllCache(),
            default            => ['success' => false, 'message' => 'Unknown action'],
        };

        SecurityAuditLog::create([
            'event'           => 'admin.quick_action',
            'severity'        => 'warning',
            'user_id'         => auth()->id(),
            'user_identifier' => auth()->user()?->email,
            'ip_address'      => $request->ip(),
            'user_agent'      => substr($request->userAgent() ?? '', 0, 255),
            'metadata'        => ['action' => $action, 'target' => $target, 'ok' => $result['success'] ?? false],
            'created_at'      => now(),
        ]);

        return response()->json($result);
    }

    public function exportAudit()
    {
        $logs = SecurityAuditLog::orderByDesc('created_at')->limit(5000)->get();
        $rows = ["Time,Event,Severity,IP Address,User,Details\n"];
        foreach ($logs as $log) {
            $meta = is_array($log->metadata)
                ? collect($log->metadata)->except(['ua', 'ip'])->map(fn ($v, $k) => "$k=$v")->implode(' | ')
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

    // ── Data assembly ─────────────────────────────────────────────────────────

    private function fullPayload(): array
    {
        $main = Cache::remember('soc_v3_main', 30, fn () => $this->buildMain());
        $sys  = Cache::remember('soc_v3_sys', 10, fn () => $this->sysMetrics());
        return array_merge($main, $sys);
    }

    private function buildMain(): array
    {
        $now = now();
        return array_merge(
            $this->score($now),
            $this->kpis($now),
            $this->charts($now),
            $this->serviceStatus(),
            $this->infraHealth(),
            $this->liveUsers($now),
            $this->aiThreats($now),
            $this->contentStats(),
            $this->geolocateAttackers($now),
            ['gmapsKey'       => config('services.google.maps_api_key', '')],
            ['auditLog'       => SecurityAuditLog::orderByDesc('created_at')->limit(100)->get()],
            ['recentCritical' => SecurityAuditLog::where('severity', 'critical')->orderByDesc('created_at')->limit(20)->get()],
        );
    }

    // ── Score ─────────────────────────────────────────────────────────────────

    private function score($now): array
    {
        $f1 = SecurityAuditLog::where('event', 'login.failed')->where('created_at', '>=', $now->copy()->subHour())->count();
        $b1 = SecurityAuditLog::where('event', 'login.blocked')->where('created_at', '>=', $now->copy()->subDay())->distinct('ip_address')->count();
        $c1 = SecurityAuditLog::where('severity', 'critical')->where('created_at', '>=', $now->copy()->subDay())->count();
        $m1 = SecurityAuditLog::where('event', 'upload.rejected')->where('created_at', '>=', $now->copy()->subDay())->count();

        $score = max(0, 100 - min(25, $f1 * 2) - min(20, $b1 * 2) - min(30, $c1 * 5) - min(25, $m1 * 5));
        $status = match (true) {
            $score >= 90 => 'healthy',
            $score >= 70 => 'warning',
            $score >= 50 => 'high_risk',
            default      => 'critical',
        };
        return ['securityScore' => $score, 'securityStatus' => $status];
    }

    // ── KPIs ──────────────────────────────────────────────────────────────────

    private function kpis($now): array
    {
        $failedLogins1h   = SecurityAuditLog::where('event', 'login.failed')->where('created_at', '>=', $now->copy()->subHour())->count();
        $failedLogins24h  = SecurityAuditLog::where('event', 'login.failed')->where('created_at', '>=', $now->copy()->subDay())->count();
        $blockedToday     = SecurityAuditLog::where('event', 'login.blocked')->whereDate('created_at', today())->count();
        $blockedIps24h    = SecurityAuditLog::where('event', 'login.blocked')->where('created_at', '>=', $now->copy()->subDay())->distinct('ip_address')->count();
        $criticalEvents1h = SecurityAuditLog::where('severity', 'critical')->where('created_at', '>=', $now->copy()->subHour())->count();
        $malware24h       = SecurityAuditLog::where('event', 'upload.rejected')->where('created_at', '>=', $now->copy()->subDay())->count();
        $totalEventsToday = SecurityAuditLog::whereDate('created_at', today())->count();

        $totalAuth  = SecurityAuditLog::whereIn('event', ['login.failed', 'login.success'])->where('created_at', '>=', $now->copy()->subHour())->count();
        $successAuth = SecurityAuditLog::where('event', 'login.success')->where('created_at', '>=', $now->copy()->subHour())->count();
        $successRate = $totalAuth > 0 ? round(($successAuth / $totalAuth) * 100, 1) : 100;

        try { $activeSessions = DB::table('personal_access_tokens')->where('last_used_at', '>=', $now->copy()->subMinutes(30))->count(); }
        catch (\Throwable) { $activeSessions = 0; }

        try { $newUsers24h = DB::table('users')->where('created_at', '>=', $now->copy()->subDay())->count(); }
        catch (\Throwable) { $newUsers24h = 0; }

        try { $totalUsers = DB::table('users')->count(); }
        catch (\Throwable) { $totalUsers = 0; }

        $rateLimitHits24h = SecurityAuditLog::where('event', 'like', '%rate%')->where('created_at', '>=', $now->copy()->subDay())->count();

        return compact(
            'failedLogins1h', 'failedLogins24h', 'blockedToday', 'blockedIps24h',
            'criticalEvents1h', 'malware24h', 'totalEventsToday', 'successRate',
            'activeSessions', 'newUsers24h', 'totalUsers', 'rateLimitHits24h'
        );
    }

    // ── Charts ────────────────────────────────────────────────────────────────

    private function charts($now): array
    {
        $raw = SecurityAuditLog::where('event', 'login.failed')
            ->where('created_at', '>=', $now->copy()->subDay())
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')->get()->keyBy('hour');

        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyData[] = ['hour' => $h, 'count' => $raw->get($h)?->count ?? 0];
        }

        $eventBreakdown = SecurityAuditLog::where('created_at', '>=', $now->copy()->subDay())
            ->selectRaw('event, COUNT(*) as count')
            ->groupBy('event')->orderByDesc('count')->limit(8)->get();

        $topIps = SecurityAuditLog::where('event', 'login.failed')
            ->where('created_at', '>=', $now->copy()->subDay())
            ->selectRaw('ip_address, COUNT(*) as attempts')
            ->groupBy('ip_address')->orderByDesc('attempts')->limit(10)->get();

        $severityData = SecurityAuditLog::where('created_at', '>=', $now->copy()->subDay())
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')->get()->keyBy('severity');

        $dailyEvents = SecurityAuditLog::where('created_at', '>=', $now->copy()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')->orderBy('date')->get();

        return compact('hourlyData', 'eventBreakdown', 'topIps', 'severityData', 'dailyEvents');
    }

    // ── Service Status ────────────────────────────────────────────────────────

    private function serviceStatus(): array
    {
        $svcs = [];

        $svcs['api']       = $this->svcCheck('API', fn () => true);
        $svcs['database']  = $this->svcCheck('Database', fn () => DB::select('SELECT 1'));
        $svcs['redis']     = $this->svcCheck('Redis', fn () => Cache::store('redis')->get('_hc'));
        $svcs['websocket'] = ['name' => 'WebSocket', 'status' => 'operational', 'latency' => null];
        $svcs['firebase']  = ['name' => 'Firebase',  'status' => 'operational', 'latency' => null];
        $svcs['payment']   = ['name' => 'Payment',   'status' => 'operational', 'latency' => null];
        $svcs['cdn']       = ['name' => 'CDN',       'status' => 'operational', 'latency' => null];
        $svcs['email']     = ['name' => 'Email',     'status' => 'operational', 'latency' => null];

        // Queue status from DB
        try {
            $failed = DB::table('failed_jobs')->count();
            $svcs['queue'] = ['name' => 'Queue', 'status' => $failed > 20 ? 'degraded' : 'operational', 'extra' => $failed . ' failed'];
        } catch (\Throwable) {
            $svcs['queue'] = ['name' => 'Queue', 'status' => 'unknown', 'latency' => null];
        }

        // Storage status
        try {
            $pct = round((1 - disk_free_space(storage_path('app/public')) / disk_total_space(storage_path('app/public'))) * 100, 1);
            $svcs['storage'] = ['name' => 'Storage', 'status' => $pct > 90 ? 'degraded' : 'operational', 'extra' => $pct . '% used'];
        } catch (\Throwable) {
            $svcs['storage'] = ['name' => 'Storage', 'status' => 'unknown', 'latency' => null];
        }

        // Overall platform status
        $hasOutage   = collect($svcs)->contains('status', 'outage');
        $hasDegraded = collect($svcs)->contains('status', 'degraded');
        $platformStatus = $hasOutage ? 'major_incident' : ($hasDegraded ? 'partial_outage' : 'operational');

        return ['services' => $svcs, 'platformStatus' => $platformStatus];
    }

    private function svcCheck(string $name, callable $fn): array
    {
        try {
            $s = microtime(true);
            $fn();
            return ['name' => $name, 'status' => 'operational', 'latency' => round((microtime(true) - $s) * 1000, 1)];
        } catch (\Throwable) {
            return ['name' => $name, 'status' => 'outage', 'latency' => null];
        }
    }

    // ── Infrastructure ────────────────────────────────────────────────────────

    private function infraHealth(): array
    {
        $infra = [];

        // MySQL
        try {
            $s = microtime(true);
            DB::select('SELECT 1');
            $infra['mysql'] = ['label' => 'MySQL', 'status' => 'healthy', 'latency' => round((microtime(true) - $s) * 1000, 1)];
        } catch (\Throwable) {
            $infra['mysql'] = ['label' => 'MySQL', 'status' => 'error', 'latency' => null];
        }

        // Redis
        try {
            $s = microtime(true);
            Cache::store('redis')->get('_p');
            $infra['redis'] = ['label' => 'Redis', 'status' => 'healthy', 'latency' => round((microtime(true) - $s) * 1000, 1)];
        } catch (\Throwable) {
            $infra['redis'] = ['label' => 'Redis', 'status' => 'warning', 'latency' => null];
        }

        // Queue
        try {
            $failed  = DB::table('failed_jobs')->count();
            $pending = DB::table('jobs')->count();
            $infra['queue'] = [
                'label'   => 'Queue Workers',
                'status'  => $failed > 20 ? 'critical' : ($failed > 5 ? 'warning' : 'healthy'),
                'failed'  => $failed,
                'pending' => $pending,
            ];
        } catch (\Throwable) {
            $infra['queue'] = ['label' => 'Queue Workers', 'status' => 'unknown', 'failed' => 0, 'pending' => 0];
        }

        // Storage
        try {
            $path  = storage_path('app/public');
            $free  = disk_free_space($path);
            $total = disk_total_space($path);
            $pct   = $total > 0 ? round((1 - $free / $total) * 100, 1) : 0;
            $infra['storage'] = [
                'label'    => 'Storage',
                'status'   => $pct > 90 ? 'critical' : ($pct > 75 ? 'warning' : 'healthy'),
                'used_pct' => $pct,
                'free_gb'  => round($free / 1073741824, 1),
                'total_gb' => round($total / 1073741824, 1),
            ];
        } catch (\Throwable) {
            $infra['storage'] = ['label' => 'Storage', 'status' => 'unknown', 'used_pct' => 0];
        }

        $infra['cache']     = ['label' => 'Cache Layer',    'status' => 'healthy', 'latency' => null];
        $infra['websocket'] = ['label' => 'Reverb WS',      'status' => 'healthy', 'latency' => null];
        $infra['scheduler'] = ['label' => 'Scheduler',      'status' => 'healthy', 'latency' => null];
        $infra['app']       = ['label' => 'Application',    'status' => 'healthy', 'latency' => null];

        return ['infra' => $infra];
    }

    // ── System Metrics (CPU, RAM, Disk, MySQL stats) ──────────────────────────

    private function sysMetrics(): array
    {
        // CPU
        $cpuLoad = function_exists('sys_getloadavg') ? sys_getloadavg() : [0.0, 0.0, 0.0];
        $cpuPct  = round(min(100, $cpuLoad[0] * 100 / max(1, (int) shell_exec('nproc 2>/dev/null') ?: 1)), 1);

        // RAM from /proc/meminfo
        $ramTotalGb = $ramUsedGb = $ramUsedPct = 0;
        $memInfo = @file_get_contents('/proc/meminfo');
        if ($memInfo) {
            preg_match('/MemTotal:\s+(\d+)/', $memInfo, $mt);
            preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $ma);
            $totalKb = (int)($mt[1] ?? 0);
            $availKb = (int)($ma[1] ?? 0);
            $usedKb  = $totalKb - $availKb;
            $ramTotalGb = round($totalKb / 1048576, 1);
            $ramUsedGb  = round($usedKb / 1048576, 1);
            $ramUsedPct = $totalKb > 0 ? round(($usedKb / $totalKb) * 100, 1) : 0;
        }

        // Disk
        $diskTotalGb = $diskFreeGb = $diskUsedGb = $diskUsedPct = 0;
        try {
            $path = storage_path('app/public');
            $free  = disk_free_space($path);
            $total = disk_total_space($path);
            $diskTotalGb = round($total / 1073741824, 1);
            $diskFreeGb  = round($free / 1073741824, 1);
            $diskUsedGb  = round(($total - $free) / 1073741824, 1);
            $diskUsedPct = $total > 0 ? round((1 - $free / $total) * 100, 1) : 0;
        } catch (\Throwable) {}

        // MySQL global status
        $mysqlQPS = $mysqlThreads = $mysqlSlowQ = 0;
        try {
            $rows = collect(DB::select("SHOW GLOBAL STATUS WHERE Variable_name IN ('Questions','Threads_connected','Slow_queries')"))
                ->keyBy('Variable_name');
            $mysqlQPS     = (int)($rows['Questions']?->Value ?? 0);
            $mysqlThreads = (int)($rows['Threads_connected']?->Value ?? 0);
            $mysqlSlowQ   = (int)($rows['Slow_queries']?->Value ?? 0);
        } catch (\Throwable) {}

        // Queue
        $failedJobs = $pendingJobs = 0;
        try { $failedJobs  = DB::table('failed_jobs')->count(); } catch (\Throwable) {}
        try { $pendingJobs = DB::table('jobs')->count(); }        catch (\Throwable) {}

        // Redis memory
        $redisMemUsed = 'N/A';
        $redisKeys    = 0;
        try {
            $info = Redis::info('memory');
            $redisMemUsed = $info['used_memory_human'] ?? 'N/A';
            $redisKeys    = Redis::dbsize();
        } catch (\Throwable) {}

        return compact(
            'cpuLoad', 'cpuPct',
            'ramTotalGb', 'ramUsedGb', 'ramUsedPct',
            'diskTotalGb', 'diskFreeGb', 'diskUsedGb', 'diskUsedPct',
            'mysqlQPS', 'mysqlThreads', 'mysqlSlowQ',
            'failedJobs', 'pendingJobs',
            'redisMemUsed', 'redisKeys'
        );
    }

    // ── Live Users ────────────────────────────────────────────────────────────

    private function liveUsers($now): array
    {
        $totalSessions = 0;
        $byRole = collect();
        $recentSessions = collect();

        try {
            $totalSessions = DB::table('personal_access_tokens')
                ->where('last_used_at', '>=', $now->copy()->subMinutes(30))
                ->count();
        } catch (\Throwable) {}

        try {
            $byRole = DB::table('personal_access_tokens as pat')
                ->join('users', 'pat.tokenable_id', '=', 'users.id')
                ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                ->where('pat.last_used_at', '>=', $now->copy()->subMinutes(30))
                ->selectRaw("COALESCE(roles.slug, 'user') as role_slug, COUNT(*) as count")
                ->groupBy('roles.slug')
                ->get()
                ->keyBy('role_slug');
        } catch (\Throwable) {}

        try {
            $recentSessions = DB::table('personal_access_tokens as pat')
                ->join('users', 'pat.tokenable_id', '=', 'users.id')
                ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                ->where('pat.last_used_at', '>=', $now->copy()->subMinutes(30))
                ->selectRaw("users.name, users.email, COALESCE(roles.name, 'Customer') as role_name, pat.last_used_at")
                ->orderByDesc('pat.last_used_at')
                ->limit(15)
                ->get();
        } catch (\Throwable) {}

        return compact('totalSessions', 'byRole', 'recentSessions');
    }

    // ── AI Threats ────────────────────────────────────────────────────────────

    private function aiThreats($now): array
    {
        $threats = [];

        // 1. Credential stuffing: 1 IP → many users
        $cs = SecurityAuditLog::where('event', 'login.failed')
            ->where('created_at', '>=', $now->copy()->subHours(6))
            ->selectRaw('ip_address, COUNT(DISTINCT user_identifier) as targets, COUNT(*) as attempts')
            ->groupBy('ip_address')
            ->having('targets', '>=', 3)
            ->orderByDesc('attempts')
            ->limit(5)
            ->get();

        foreach ($cs as $r) {
            $threats[] = [
                'type'       => 'Credential Stuffing',
                'icon'       => 'fa-user-secret',
                'risk'       => 'critical',
                'confidence' => min(99, 60 + ($r->targets * 5) + ($r->attempts * 2)),
                'detail'     => "IP {$r->ip_address} targeted {$r->targets} accounts ({$r->attempts} attempts in 6h)",
                'source'     => $r->ip_address,
                'action'     => 'Block IP',
                'auto'       => false,
            ];
        }

        // 2. Brute force: same user, many attempts
        $bf = SecurityAuditLog::where('event', 'login.failed')
            ->where('created_at', '>=', $now->copy()->subHour())
            ->selectRaw('user_identifier, COUNT(*) as attempts, COUNT(DISTINCT ip_address) as ips')
            ->groupBy('user_identifier')
            ->whereNotNull('user_identifier')
            ->having('attempts', '>=', 5)
            ->orderByDesc('attempts')
            ->limit(5)
            ->get();

        foreach ($bf as $r) {
            $threats[] = [
                'type'       => 'Brute Force',
                'icon'       => 'fa-hammer',
                'risk'       => $r->attempts >= 15 ? 'critical' : 'high',
                'confidence' => min(99, 70 + ($r->attempts * 2)),
                'detail'     => "{$r->attempts} attempts on {$r->user_identifier} from {$r->ips} IPs",
                'source'     => $r->user_identifier,
                'action'     => 'Lock Account',
                'auto'       => true,
            ];
        }

        // 3. Malware upload attempts
        $mu = SecurityAuditLog::where('event', 'upload.rejected')
            ->where('created_at', '>=', $now->copy()->subHours(6))
            ->selectRaw('ip_address, COUNT(*) as attempts')
            ->groupBy('ip_address')
            ->having('attempts', '>=', 2)
            ->orderByDesc('attempts')
            ->limit(3)
            ->get();

        foreach ($mu as $r) {
            $threats[] = [
                'type'       => 'Malware Upload',
                'icon'       => 'fa-virus',
                'risk'       => 'critical',
                'confidence' => min(99, 75 + ($r->attempts * 5)),
                'detail'     => "IP {$r->ip_address}: {$r->attempts} malicious file upload attempts",
                'source'     => $r->ip_address,
                'action'     => 'Block IP',
                'auto'       => true,
            ];
        }

        // 4. API abuse (rate limit hits)
        $rl = SecurityAuditLog::where('event', 'like', '%rate%')
            ->where('created_at', '>=', $now->copy()->subHour())
            ->selectRaw('ip_address, COUNT(*) as hits')
            ->groupBy('ip_address')
            ->having('hits', '>=', 3)
            ->orderByDesc('hits')
            ->limit(3)
            ->get();

        foreach ($rl as $r) {
            $threats[] = [
                'type'       => 'API Abuse',
                'icon'       => 'fa-robot',
                'risk'       => 'high',
                'confidence' => min(95, 50 + ($r->hits * 10)),
                'detail'     => "IP {$r->ip_address}: {$r->hits} rate limit violations in 1h",
                'source'     => $r->ip_address,
                'action'     => 'Throttle',
                'auto'       => true,
            ];
        }

        // 5. Admin panel brute force
        $ab = SecurityAuditLog::where('event', 'admin.login.failed')
            ->where('created_at', '>=', $now->copy()->subHour())
            ->selectRaw('ip_address, COUNT(*) as attempts, COUNT(DISTINCT user_identifier) as targets')
            ->groupBy('ip_address')
            ->having('attempts', '>=', 3)
            ->orderByDesc('attempts')
            ->limit(5)
            ->get();

        foreach ($ab as $r) {
            $threats[] = [
                'type'       => 'Admin Brute Force',
                'icon'       => 'fa-shield-halved',
                'risk'       => $r->attempts >= 8 ? 'critical' : 'high',
                'confidence' => min(99, 80 + ($r->attempts * 3)),
                'detail'     => "IP {$r->ip_address}: {$r->attempts} admin login attempts ({$r->targets} account(s) targeted)",
                'source'     => $r->ip_address,
                'action'     => 'Block IP',
                'auto'       => false,
                'vector'     => 'admin_panel',
            ];
        }

        // 6. Admin route scanning / probing
        $probe = SecurityAuditLog::whereIn('event', ['admin.route.probe', 'admin.route.blocked'])
            ->where('created_at', '>=', $now->copy()->subHour())
            ->selectRaw('ip_address, COUNT(*) as cnt, MAX(event) as worst')
            ->groupBy('ip_address')
            ->having('cnt', '>=', 3)
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        foreach ($probe as $r) {
            $threats[] = [
                'type'       => 'Admin Panel Scan',
                'icon'       => 'fa-magnifying-glass',
                'risk'       => $r->worst === 'admin.route.blocked' ? 'critical' : 'high',
                'confidence' => min(99, 65 + ($r->cnt * 2)),
                'detail'     => "IP {$r->ip_address} probing admin routes — {$r->cnt} unauthorized requests in 1h",
                'source'     => $r->ip_address,
                'action'     => 'Block IP',
                'auto'       => false,
                'vector'     => 'admin_panel',
            ];
        }

        // 7. Privilege escalation attempts
        $pe = SecurityAuditLog::whereIn('event', ['admin.access.denied', 'admin.privilege.escalation'])
            ->where('created_at', '>=', $now->copy()->subHours(24))
            ->selectRaw('ip_address, user_identifier, COUNT(*) as cnt')
            ->groupBy('ip_address', 'user_identifier')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        foreach ($pe as $r) {
            $threats[] = [
                'type'       => 'Privilege Escalation',
                'icon'       => 'fa-user-lock',
                'risk'       => 'critical',
                'confidence' => 95,
                'detail'     => ($r->user_identifier ? "User {$r->user_identifier}" : "IP {$r->ip_address}") . " attempted admin access without permission ({$r->cnt}×)",
                'source'     => $r->ip_address ?? $r->user_identifier,
                'action'     => 'Revoke Session',
                'auto'       => false,
                'vector'     => 'admin_panel',
            ];
        }

        usort($threats, fn ($a, $b) => (
            (['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1][$b['risk']] ?? 0) -
            (['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1][$a['risk']] ?? 0)
        ) ?: $b['confidence'] - $a['confidence']);

        return ['aiThreats' => $threats, 'threatCount' => count($threats)];
    }

    // ── Content ───────────────────────────────────────────────────────────────

    private function contentStats(): array
    {
        $contentStats = [];
        $tables = [
            'pending_moderation' => fn () => DB::table('community_posts')->where('moderation_status', 'pending')->count(),
            'flagged_today'      => fn () => DB::table('community_posts')->where('moderation_status', 'flagged')->whereDate('created_at', today())->count(),
            'pending_reports'    => fn () => DB::table('community_reports')->where('status', 'pending')->count(),
            'total_posts'        => fn () => DB::table('community_posts')->count(),
        ];
        foreach ($tables as $key => $fn) {
            try { $contentStats[$key] = $fn(); } catch (\Throwable) { $contentStats[$key] = 0; }
        }
        return compact('contentStats');
    }

    // ── IP Geolocation (real attacker positions) ──────────────────────────────

    private function geolocateAttackers($now): array
    {
        $attackerGeo = Cache::remember('soc_attacker_geo', 300, function () use ($now) {
            // Get unique attacker IPs with event types (last 48h)
            $rows = SecurityAuditLog::where('created_at', '>=', $now->copy()->subHours(48))
                ->whereNotNull('ip_address')
                ->whereIn('event', ['login.failed', 'login.blocked', 'upload.rejected', 'rate_limit'])
                ->selectRaw('ip_address, event, COUNT(*) as cnt')
                ->groupBy('ip_address', 'event')
                ->orderByDesc('cnt')
                ->limit(80)
                ->get();

            if ($rows->isEmpty()) {
                return [];
            }

            // Filter out private / loopback IPs
            $public = $rows->filter(fn ($r) => filter_var(
                $r->ip_address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ))->values();

            if ($public->isEmpty()) {
                return [];
            }

            // Batch geolocate via ip-api.com (free, server-side HTTP, cached 1h)
            $batch = $public->map(fn ($r) => ['query' => $r->ip_address, 'fields' => 'status,lat,lon,country,city,query'])->toArray();

            try {
                $resp = Http::timeout(8)->post('http://ip-api.com/batch?fields=status,lat,lon,country,city,query', $batch);
                if (! $resp->ok()) {
                    return [];
                }
                $geo = $resp->json();
            } catch (\Throwable) {
                return [];
            }

            $result = [];
            $seen   = [];

            foreach ($geo as $i => $g) {
                if (($g['status'] ?? '') !== 'success') {
                    continue;
                }
                $ip = $g['query'] ?? ($public[$i]?->ip_address ?? null);
                if (! $ip || isset($seen[$ip])) {
                    continue;
                }
                $seen[$ip] = true;

                $row  = $public->firstWhere('ip_address', $ip) ?? $public[$i];
                $type = match ($row?->event ?? '') {
                    'upload.rejected'           => 'malware_upload',
                    'login.blocked'             => 'credential_stuffing',
                    'rate_limit'                => 'api_abuse',
                    default                     => 'brute_force',
                };

                $result[] = [
                    'ip'      => $ip,
                    'lat'     => (float) $g['lat'],
                    'lng'     => (float) $g['lon'],
                    'country' => $g['country'] ?? '',
                    'city'    => $g['city'] ?? '',
                    'cnt'     => (int) ($row?->cnt ?? 1),
                    'type'    => $type,
                ];
            }

            return $result;
        });

        return ['attackerGeo' => $attackerGeo];
    }

    // ── Quick Action Handlers ─────────────────────────────────────────────────

    private function doBlockIp(string $ip): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'message' => 'Invalid IP address'];
        }
        Cache::put('soc_blocked_' . md5($ip), $ip, now()->addHours(24));
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
        return ['success' => true, 'message' => 'Queue restart signal sent'];
    }

    private function doClearViewCache(): array
    {
        Artisan::call('view:clear');
        return ['success' => true, 'message' => 'View cache cleared'];
    }

    private function doClearAllCache(): array
    {
        Cache::flush();
        Artisan::call('view:clear');
        Artisan::call('config:cache');
        return ['success' => true, 'message' => 'All caches cleared and config re-cached'];
    }
}
