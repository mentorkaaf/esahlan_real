<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Deliveryman;
use App\Services\FcmService;

/**
 * DriverLocationPinger — runs as a Supervisor daemon (never exits).
 *
 * Pings every 30 seconds via FCM to drivers whose foreground service
 * may be dead (killed app). Flutter _bgHandler receives the silent FCM
 * and calls postLocationForFcm() → GPS → POST /delivery/location.
 *
 * This gives near-real-time location (~30s gap) even for killed apps,
 * as long as internet is available. The foreground service (5s interval)
 * remains the primary path when the app is alive.
 *
 * Start:   supervisorctl start esahlan-location-pinger
 * Stop:    supervisorctl stop esahlan-location-pinger
 * Logs:    /var/log/esahlan/location-pinger.log
 */
class DriverLocationPinger extends Command
{
    protected $signature   = 'drivers:location-pinger';
    protected $description = 'Daemon: pings wants_tracking drivers every 30s via FCM (Supervisor managed)';

    public function handle(): int
    {
        $this->info('[Pinger] Started — pinging every 30 seconds');

        while (true) {
            try {
                $this->ping();
            } catch (\Throwable $e) {
                $this->error('[Pinger] Error: ' . $e->getMessage());
            }

            sleep(30);
        }

        return Command::SUCCESS;
    }

    private function ping(): void
    {
        $stale = now()->subSeconds(25); // ping if no location in last 25s

        // Ping ALL drivers with FCM token — ignoring wants_tracking / is_online.
        // Native watchdog posts location and auto-restores is_online=true.
        $rows = Deliveryman::whereNotNull('user_id')
            ->where(function ($q) use ($stale) {
                $q->whereNull('last_location_at')
                  ->orWhere('last_location_at', '<', $stale);
            })
            ->join('users', 'users.id', '=', 'deliverymen.user_id')
            ->whereNotNull('users.fcm_token')
            ->where('users.fcm_token', '!=', '')
            ->select('deliverymen.id', 'users.fcm_token', 'deliverymen.last_location_at')
            ->get();

        if ($rows->isEmpty()) {
            return; // all drivers are posting live — no pings needed
        }

        $sent = 0;
        foreach ($rows as $row) {
            $ok = FcmService::sendDataOnly($row->fcm_token, [
                'type' => 'request_location',
                'ts'   => (string) time(),
            ]);
            if ($ok) $sent++;
        }

        if ($sent > 0) {
            $this->line('[Pinger] ' . now()->format('H:i:s') . " — pinged {$sent}/{$rows->count()} stale drivers");
        }
    }
}
