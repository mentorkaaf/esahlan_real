<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FcmService;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Deliveryman;
use Illuminate\Support\Facades\DB;

/**
 * RefreshFcmTokens — runs daily via scheduler.
 *
 * Sends a silent `token_check` data-only FCM ping to every user, vendor, and
 * driver that has a stored FCM token. The app's background handler receives
 * the ping and immediately re-uploads its current FCM token to the server.
 *
 * Two outcomes per ping:
 *   200  → app wakes in background, Flutter _bgHandler checks and re-uploads
 *          the token if it has changed since last upload.
 *   404/403 → token is stale (app reinstalled / Firebase rotated) → FcmService
 *          auto-clears the token in the DB so we stop sending to it.
 *
 * This guarantees that even users who haven't opened the app for months will
 * keep receiving notifications as long as FCM can still deliver to their device.
 */
class RefreshFcmTokens extends Command
{
    protected $signature   = 'fcm:refresh-tokens';
    protected $description = 'Send silent token_check ping to all devices to force token re-upload';

    public function handle(): int
    {
        $data = ['type' => 'token_check', 'ts' => (string) time()];
        $sent = 0;
        $cleared = 0;

        // ── Customers ─────────────────────────────────────────────────────────
        $customers = User::whereNotNull('fcm_token')->where('fcm_token', '!=', '')->get(['id', 'fcm_token']);
        foreach ($customers as $user) {
            $ok = FcmService::sendDataOnly($user->fcm_token, $data);
            $ok ? $sent++ : $cleared++;
        }
        $this->line("[token-refresh] Customers: {$sent} woken, {$cleared} cleared");

        // ── Vendors ───────────────────────────────────────────────────────────
        $vSent = 0; $vCleared = 0;
        $vendors = Vendor::whereNotNull('vendor_fcm_token')->where('vendor_fcm_token', '!=', '')->get(['id', 'vendor_fcm_token']);
        foreach ($vendors as $vendor) {
            $ok = FcmService::sendDataOnly($vendor->vendor_fcm_token, $data);
            $ok ? $vSent++ : $vCleared++;
        }
        $this->line("[token-refresh] Vendors: {$vSent} woken, {$vCleared} cleared");

        // ── Drivers (via users table fcm_token joined to deliverymen) ─────────
        $dSent = 0; $dCleared = 0;
        $drivers = DB::table('deliverymen')
            ->join('users', 'users.id', '=', 'deliverymen.user_id')
            ->whereNotNull('users.fcm_token')
            ->where('users.fcm_token', '!=', '')
            ->select('users.fcm_token')
            ->get();
        foreach ($drivers as $d) {
            $ok = FcmService::sendDataOnly($d->fcm_token, $data);
            $ok ? $dSent++ : $dCleared++;
        }
        $this->line("[token-refresh] Drivers: {$dSent} woken, {$dCleared} cleared");

        $total = $sent + $vSent + $dSent;
        $totalCleared = $cleared + $vCleared + $dCleared;
        $this->info("[token-refresh] Done — {$total} devices woken, {$totalCleared} stale tokens cleared");

        return Command::SUCCESS;
    }
}
