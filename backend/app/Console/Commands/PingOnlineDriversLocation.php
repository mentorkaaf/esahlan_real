<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Deliveryman;
use App\Services\FcmService;
use Illuminate\Support\Facades\DB;

/**
 * PingOnlineDriversLocation
 *
 * Runs every 5 minutes via the scheduler.
 *
 * Sends a SILENT (data-only, no notification block) FCM message to every
 * driver that has is_online=true AND an FCM token AND hasn't posted a
 * location in the last 3 minutes.
 *
 * Why data-only?  A message with a 'notification' block is shown to the
 * driver as a visible alert.  A data-only FCM message is delivered silently;
 * Android always routes it to the Flutter background handler (_bgHandler)
 * regardless of whether the app is foreground, background, or killed.
 *
 * Flutter _bgHandler response (firebase_service.dart):
 *   type == 'request_location' → postLocationForFcm() → _postLocationHttp()
 *   → GPS position read → POST /api/v1/deliveryman/location
 *
 * Effect: the driver's location appears on the Live Driver Map in admin
 * even if the driver hasn't opened the app for days — as long as:
 *   ✓ App is installed
 *   ✓ Internet is connected
 *   ✓ FCM token is valid
 *   ✓ Android location permission is granted (always / while-in-use)
 *   ✓ is_online = true in the database (driver set it via the toggle)
 */
class PingOnlineDriversLocation extends Command
{
    protected $signature   = 'drivers:ping-location';
    protected $description = 'Send silent FCM to online drivers to post their GPS location now';

    public function handle(): int
    {
        // Only ping drivers who:
        //   1. are marked online
        //   2. have an FCM token
        //   3. haven't posted a location in the last 3 minutes
        //      (if they're posting every 5 s via foreground service,
        //       they don't need a wake-up ping — saves FCM quota)
        $staleThreshold = now()->subMinutes(3);

        // Use wants_tracking (the driver's INTENT) not is_online (current freshness).
        // MarkStaleDriversOffline clears is_online after 10-min silence but never
        // touches wants_tracking, so we still wake up drivers whose foreground
        // service died and haven't opened the app for days.
        $rows = Deliveryman::where('wants_tracking', true)
            ->whereNotNull('user_id')
            ->where(function ($q) use ($staleThreshold) {
                $q->whereNull('last_location_at')
                  ->orWhere('last_location_at', '<', $staleThreshold);
            })
            ->join('users', 'users.id', '=', 'deliverymen.user_id')
            ->whereNotNull('users.fcm_token')
            ->where('users.fcm_token', '!=', '')
            ->select('deliverymen.id', 'users.fcm_token')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No stale online drivers to ping.');
            return Command::SUCCESS;
        }

        $sent   = 0;
        $failed = 0;

        foreach ($rows as $row) {
            // Send a silent data-only FCM — no notification shown to driver
            $ok = FcmService::sendDataOnly($row->fcm_token, [
                'type' => 'request_location',
                'ts'   => (string) time(),
            ]);

            $ok ? $sent++ : $failed++;
        }

        $this->info("Location ping sent: {$sent} ok, {$failed} failed (of {$rows->count()} stale online drivers).");
        return Command::SUCCESS;
    }
}
