<?php

namespace App\Console\Commands;

use App\Services\AdminAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Checks server health every 5 minutes and sends admin alerts when thresholds
 * are exceeded. Runs via Laravel Scheduler — no manual invocation needed.
 *
 * Checks:
 *  - CPU load (>85%)
 *  - Memory usage (>90%)
 *  - Disk usage (>80% warning, >90% critical)
 *  - Failed queue jobs (>10)
 *  - Slow DB queries (>50 in last hour)
 *  - Failed login spike (>20 in 10 min)
 */
class AdminServerHealthCheck extends Command
{
    protected $signature   = 'admin:server-health-check';
    protected $description = 'Check server health and send admin alert emails if thresholds exceeded';

    public function handle(): void
    {
        $this->checkCpu();
        $this->checkMemory();
        $this->checkDisk();
        $this->checkQueueJobs();
        $this->checkSlowDb();
        $this->checkFailedLogins();
    }

    // ── CPU ───────────────────────────────────────────────────────────────────

    private function checkCpu(): void
    {
        if (!file_exists('/proc/loadavg')) return;
        $load = (float) explode(' ', file_get_contents('/proc/loadavg'))[0];
        $cores = (int) shell_exec('nproc') ?: 1;
        $pct = ($load / $cores) * 100;

        if ($pct >= 85) {
            AdminAlertService::send('high_cpu', "⚠️ High CPU Usage: {$pct}%", [
                'CPU Load (1min)' => round($pct, 1) . '%',
                'Load Average'    => $load,
                'CPU Cores'       => $cores,
                'Threshold'       => '85%',
                'Checked At'      => now()->format('d M Y H:i') . ' UTC',
            ], "high_cpu", 900); // max 1 alert per 15 min
        }
    }

    // ── Memory ────────────────────────────────────────────────────────────────

    private function checkMemory(): void
    {
        if (!file_exists('/proc/meminfo')) return;
        $info = [];
        foreach (explode("\n", file_get_contents('/proc/meminfo')) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                $info[$m[1]] = (int)$m[2];
            }
        }
        $total     = $info['MemTotal']     ?? 0;
        $available = $info['MemAvailable'] ?? 0;
        if (!$total) return;

        $usedPct = round((($total - $available) / $total) * 100, 1);
        $usedGb  = round(($total - $available) / 1024 / 1024, 2);
        $totalGb = round($total / 1024 / 1024, 2);

        if ($usedPct >= 90) {
            AdminAlertService::send('high_memory', "⚠️ High Memory Usage: {$usedPct}%", [
                'Memory Used'  => "{$usedGb} GB / {$totalGb} GB ({$usedPct}%)",
                'Available'    => round($available / 1024 / 1024, 2) . ' GB',
                'Threshold'    => '90%',
                'Checked At'   => now()->format('d M Y H:i') . ' UTC',
            ], "high_memory", 900);
        }
    }

    // ── Disk ─────────────────────────────────────────────────────────────────

    private function checkDisk(): void
    {
        $total = disk_total_space('/');
        $free  = disk_free_space('/');
        if (!$total) return;

        $used    = $total - $free;
        $usedPct = round(($used / $total) * 100, 1);
        $usedGb  = round($used / 1024 / 1024 / 1024, 1);
        $totalGb = round($total / 1024 / 1024 / 1024, 1);
        $freeGb  = round($free / 1024 / 1024 / 1024, 1);

        $data = [
            'Disk Used'  => "{$usedGb} GB / {$totalGb} GB ({$usedPct}%)",
            'Free Space' => "{$freeGb} GB",
            'Checked At' => now()->format('d M Y H:i') . ' UTC',
        ];

        if ($usedPct >= 90) {
            $data['Threshold'] = '90% — CRITICAL';
            AdminAlertService::send('storage_critical', "🆘 Storage CRITICAL: {$usedPct}% used!", $data, "storage_critical", 1800);
        } elseif ($usedPct >= 80) {
            $data['Threshold'] = '80% — Warning';
            AdminAlertService::send('storage_warning', "⚠️ Storage Warning: {$usedPct}% used", $data, "storage_warning", 3600);
        }
    }

    // ── Queue failed jobs ─────────────────────────────────────────────────────

    private function checkQueueJobs(): void
    {
        try {
            $failed = DB::table('failed_jobs')->count();
            if ($failed >= 10) {
                AdminAlertService::send('queue_failed', "⚙️ {$failed} Failed Queue Jobs", [
                    'Failed Jobs' => $failed,
                    'Action'      => 'Run: php artisan queue:retry all',
                    'Checked At'  => now()->format('d M Y H:i') . ' UTC',
                ], "queue_failed", 3600);
            }
        } catch (\Throwable) {}
    }

    // ── Slow DB queries ───────────────────────────────────────────────────────

    private function checkSlowDb(): void
    {
        try {
            // Count slow queries logged in the last hour
            $slowCount = DB::table('query_logs')
                ->where('duration_ms', '>', 1000)
                ->where('created_at', '>', now()->subHour())
                ->count();

            if ($slowCount >= 50) {
                AdminAlertService::send('slow_db', "🐢 {$slowCount} Slow DB Queries in Last Hour", [
                    'Slow Queries (>1s)' => $slowCount . ' in last 60 min',
                    'Threshold'          => '50 per hour',
                    'Action'             => 'Check Admin → Security → Performance tab',
                    'Checked At'         => now()->format('d M Y H:i') . ' UTC',
                ], "slow_db", 3600);
            }
        } catch (\Throwable) {}
    }

    // ── Failed login spike (brute force) ─────────────────────────────────────

    private function checkFailedLogins(): void
    {
        try {
            // Count failed admin logins in last 10 minutes from security_events table
            $query = DB::table('security_events')
                ->where('type', 'admin.login.blocked')
                ->where('created_at', '>', now()->subMinutes(10));

            if (!DB::getSchemaBuilder()->hasTable('security_events')) return;

            $count = $query->count();
            if ($count >= 20) {
                $latestIp = DB::table('security_events')
                    ->where('type', 'admin.login.blocked')
                    ->where('created_at', '>', now()->subMinutes(10))
                    ->latest()
                    ->value('ip');

                AdminAlertService::send('failed_login_spike', "🚨 Brute Force Detected! {$count} Failed Login Attempts", [
                    'Failed Attempts'  => $count . ' in last 10 minutes',
                    'Latest IP'        => $latestIp ?? 'Unknown',
                    'Target'           => 'Admin Panel Login',
                    'Action'           => 'Check Admin → Security → SOC',
                    'Checked At'       => now()->format('d M Y H:i') . ' UTC',
                ], "failed_login_spike", 600); // max 1 per 10 min
            }
        } catch (\Throwable) {}
    }
}
