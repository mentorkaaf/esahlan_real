<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\DiscountCampaign;
use App\Models\Order;
use App\Models\User;
use App\Services\FcmService;

/**
 * Sends FCM push notifications for active eFood discount campaigns.
 *
 * Runs every 2 hours via the scheduler.
 *
 * Logic:
 *  - Find all live campaigns (active, started, not expired)
 *  - For each campaign, find users who:
 *      1. Have a valid FCM token
 *      2. Have NOT already ordered from that restaurant during the campaign window
 *      3. Have NOT been notified about THIS campaign in the last 2 hours
 *  - Send a rich FCM notification with discount details
 *  - Mark notified user+campaign pairs in Redis (TTL 2h) to avoid spam
 */
class SendCampaignDiscountNotifications extends Command
{
    protected $signature   = 'efood:send-campaign-notifications {--dry-run : Show what would be sent without sending}';
    protected $description = 'Send FCM notifications to users about active eFood discount campaigns (every 2h)';

    // Cache TTL — matches the scheduler interval so each user gets at most 1 notif per window
    private const NOTIF_TTL = 7200; // 2 hours in seconds

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // ── 1. Get all live campaigns ────────────────────────────────────────
        $now       = now();
        $campaigns = DiscountCampaign::with(['vendor:id,name,logo', 'category:id,name'])
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at',   '>=', $now)
            ->whereHas('vendor', fn($q) => $q->where('module_slug', 'efood'))
            ->get();

        if ($campaigns->isEmpty()) {
            $this->info('No active eFood campaigns found.');
            return 0;
        }

        $this->info("Found {$campaigns->count()} active campaign(s).");

        // ── 2. Get all users with FCM tokens ─────────────────────────────────
        $users = User::whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('status', '!=', 'banned')
            ->select('id', 'fcm_token')
            ->get()
            ->keyBy('id');

        if ($users->isEmpty()) {
            $this->info('No users with FCM tokens.');
            return 0;
        }

        $totalSent = 0;

        foreach ($campaigns as $campaign) {
            $vendorId    = $campaign->vendor_id;
            $vendorName  = $campaign->vendor?->name ?? 'Restaurant';
            $logoUrl     = $campaign->vendor?->logo;
            $catName     = $campaign->category?->name;
            $discountStr = $campaign->discount_type === 'percentage'
                ? "{$campaign->discount_value}% OFF"
                : '$' . number_format($campaign->discount_value, 0) . ' OFF';

            // ── 3. Users who ordered from this restaurant during campaign ────
            $purchasedUserIds = Order::where('vendor_id', $vendorId)
                ->where('module_slug', 'efood')
                ->whereBetween('created_at', [$campaign->starts_at, $campaign->ends_at])
                ->whereNotIn('status', ['cancelled', 'rejected', 'failed'])
                ->pluck('user_id')
                ->unique()
                ->flip(); // flip for O(1) lookup

            // ── 4. Build notification content ────────────────────────────────
            $title = "🔥 {$discountStr} at {$vendorName}!";
            $body  = $catName
                ? "Get {$discountStr} on {$catName} at {$vendorName}. Offer ends " . $campaign->ends_at->format('M j, g:ia') . '!'
                : "Get {$discountStr} at {$vendorName}. Offer ends " . $campaign->ends_at->format('M j, g:ia') . '!';

            $data = [
                'type'        => 'discount_campaign',
                'campaign_id' => (string) $campaign->id,
                'vendor_id'   => (string) $vendorId,
                'module'      => 'efood',
                'route'       => '/efood/vendor/' . $vendorId,
            ];

            $sent = 0;

            foreach ($users as $userId => $user) {
                // Skip users who already purchased during this campaign
                if (isset($purchasedUserIds[$userId])) {
                    continue;
                }

                // Skip users already notified about this campaign in this 2h window
                $cacheKey = "camp_notif:{$campaign->id}:{$userId}";
                if (Cache::has($cacheKey)) {
                    continue;
                }

                if ($dryRun) {
                    $this->line("  [DRY] Would notify user #{$userId} → campaign #{$campaign->id} ({$vendorName})");
                } else {
                    $ok = FcmService::sendToToken(
                        fcmToken:  $user->fcm_token,
                        title:     $title,
                        body:      $body,
                        data:      $data,
                        imageUrl:  $logoUrl,
                        channelId: 'esahlan_promo',
                    );

                    if ($ok) {
                        // Mark as notified for 2h to prevent re-sending in same window
                        Cache::put($cacheKey, 1, self::NOTIF_TTL);
                        $sent++;
                    }
                }
            }

            $totalSent += $sent;
            $this->info("  Campaign #{$campaign->id} ({$vendorName}): sent {$sent} notifications" . ($dryRun ? ' [DRY RUN]' : ''));

            Log::info('[CampaignNotif] Campaign #' . $campaign->id . ' sent=' . $sent . ' vendor=' . $vendorName);
        }

        $this->info("Done. Total notifications sent: {$totalSent}");
        return 0;
    }
}
