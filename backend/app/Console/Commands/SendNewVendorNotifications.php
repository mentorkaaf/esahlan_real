<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\FcmService;

/**
 * Notifies users in a district when a new vendor opens nearby.
 *
 * Runs every hour. Finds vendors approved in the last hour, then
 * sends a push notification to all users in the same district.
 *
 * Cache prevents spamming the same users for the same vendor (72h TTL).
 * Template variables: {store_name}, {module}, {district}
 *
 * Admin can enable/disable via Auto Notifications panel.
 */
class SendNewVendorNotifications extends Command
{
    protected $signature = 'marketing:new-vendor
                            {--vendor= : Send notification for a specific vendor ID only}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Notify district users when a new vendor joins eSahlan nearby';

    private const SLUG = 'new_vendor_district';

    public function handle(): int
    {
        $dryRun   = $this->option('dry-run');
        $vendorId = $this->option('vendor');

        // ── Load template ─────────────────────────────────────────────────────
        $template = DB::table('auto_notification_templates')->where('slug', self::SLUG)->first();

        if ($template && !$template->is_active) {
            $this->info('new_vendor_district notifications are DISABLED by admin.');
            return 0;
        }

        $language  = $template?->language ?? 'en';
        $titleTpl  = ($language === 'so' && $template?->title_so)
            ? $template->title_so
            : ($template?->title_template ?? '🏪 New {module} opened near you!');
        $bodyTpl   = ($language === 'so' && $template?->body_so)
            ? $template->body_so
            : ($template?->body_template ?? '{store_name} just joined eSahlan in {district}. Check them out!');

        // ── Find recently approved vendors ────────────────────────────────────
        try {
            $query = DB::table('vendors as v')
                ->leftJoin('modules as m', 'm.id', '=', 'v.module_id')
                ->select(
                    'v.id', 'v.name as store_name', 'v.district_id',
                    'v.approved_at', 'v.logo',
                    'm.name as module_name',
                )
                ->where(function ($q) {
                    $q->where('v.is_approved', 1)
                      ->where(function ($q2) {
                          $q2->where('v.approved_at', '>=', now()->subHour())
                             ->orWhere(function ($q3) {
                                 $q3->whereNull('v.approved_at')
                                    ->where('v.updated_at', '>=', now()->subHour());
                             });
                      });
                });

            if ($vendorId) {
                $query->where('v.id', $vendorId);
            }

            $vendors = $query->get();
        } catch (\Throwable $e) {
            Log::warning('[NewVendorNotif] Could not query vendors: ' . $e->getMessage());
            $this->error('Failed to query vendors: ' . $e->getMessage());
            return 0;
        }

        if ($vendors->isEmpty()) {
            $this->info('No new vendors found in the last hour.');
            return 0;
        }

        $this->info("Found {$vendors->count()} new vendor(s).");

        $totalSent = 0;

        foreach ($vendors as $vendor) {
            $cacheVendorBase = "new_vendor:{$vendor->id}";

            // ── Find district name ──────────────────────────────────────────
            $districtName = 'your area';
            try {
                $district = DB::table('districts')->where('id', $vendor->district_id)->first();
                if ($district) {
                    $districtName = $district->name ?? $districtName;
                }
            } catch (\Throwable) {}

            // ── Build notification ──────────────────────────────────────────
            $moduleName = $vendor->module_name ?? 'Store';
            $replace = [
                '{store_name}' => $vendor->store_name ?? 'A new store',
                '{module}'     => $moduleName,
                '{district}'   => $districtName,
            ];
            $title = str_replace(array_keys($replace), array_values($replace), $titleTpl);
            $body  = str_replace(array_keys($replace), array_values($replace), $bodyTpl);

            $data = [
                'type'      => 'new_vendor',
                'vendor_id' => (string) $vendor->id,
                'module'    => strtolower(str_replace(' ', '', $moduleName)),
                'deep_link' => '/vendor/' . $vendor->id,
            ];

            // ── Find users in same district ─────────────────────────────────
            try {
                $users = User::where('district_id', $vendor->district_id)
                    ->whereNotNull('fcm_token')
                    ->where('fcm_token', '!=', '')
                    ->where('status', '!=', 'banned')
                    ->select('id', 'fcm_token')
                    ->get();
            } catch (\Throwable $e) {
                // Fallback: no district_id column — send to all users with FCM
                Log::info('[NewVendorNotif] district_id column not on users — falling back to all users.');
                $users = User::whereNotNull('fcm_token')
                    ->where('fcm_token', '!=', '')
                    ->where('status', '!=', 'banned')
                    ->select('id', 'fcm_token')
                    ->limit(500) // Safety cap
                    ->get();
            }

            if ($users->isEmpty()) {
                $this->line("  Vendor #{$vendor->id} ({$vendor->store_name}): no users in district {$districtName}.");
                continue;
            }

            $this->info("  Vendor #{$vendor->id} ({$vendor->store_name}) in {$districtName}: {$users->count()} potential users.");

            $sent = 0;
            foreach ($users as $user) {
                $cacheKey = "{$cacheVendorBase}:{$user->id}";
                if (Cache::has($cacheKey)) continue;

                if ($dryRun) {
                    $this->line("    [DRY] → user#{$user->id} | {$title}");
                } else {
                    $ok = FcmService::sendToToken(
                        fcmToken:  $user->fcm_token,
                        title:     $title,
                        body:      $body,
                        data:      $data,
                        imageUrl:  $vendor->logo ?? null,
                        channelId: $template?->channel_id ?? 'esahlan_promo',
                    );
                    if ($ok) {
                        Cache::put($cacheKey, 1, 72 * 3600); // 72h — don't spam same vendor
                        $sent++;
                    }
                }
            }

            $totalSent += $sent;
            $this->info("  Vendor #{$vendor->id}: sent {$sent}" . ($dryRun ? ' [DRY]' : ''));

            if (!$dryRun && $sent > 0) {
                DB::table('auto_notification_logs')->insert([
                    'template_slug'  => self::SLUG,
                    'reference_id'   => $vendor->id,
                    'reference_type' => 'vendor',
                    'title'          => $title,
                    'body'           => $body,
                    'sent_count'     => $sent,
                    'created_at'     => now(),
                ]);
            }
        }

        if (!$dryRun) {
            DB::table('auto_notification_templates')
                ->where('slug', self::SLUG)
                ->update(['last_sent_at' => now()]);
        }

        $this->info("Done. Total sent: {$totalSent}");
        Log::info("[NewVendorNotif] vendors={$vendors->count()} total_sent={$totalSent}");
        return 0;
    }
}
