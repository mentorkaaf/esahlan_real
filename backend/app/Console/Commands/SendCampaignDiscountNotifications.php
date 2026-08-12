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
 * Runs every 2 hours (normal) via scheduler.
 * Runs every 30 minutes with --urgent flag when campaign has ≤ 2 hours left.
 *
 * Features:
 *  - Deep link: data['deep_link'] → Flutter navigates to vendor page on tap
 *  - Excludes users who have already ordered during the campaign window
 *  - Redis cache prevents duplicate notifications within the same send window
 *  - Urgent mode: last 2 hours of campaign → 30-min interval, 2x per hour
 */
class SendCampaignDiscountNotifications extends Command
{
    protected $signature = 'efood:send-campaign-notifications
                            {--urgent  : Only campaigns ending ≤2h from now, 30-min cache TTL}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send FCM notifications to users about active eFood discount campaigns';

    // Normal mode: 2-hour cache = 1 notification per 2h window
    private const NORMAL_TTL = 7200;   // 2h

    // Urgent mode (last 2h of campaign): 30-min cache = up to 2 notifications/hour
    private const URGENT_TTL = 1800;   // 30 min

    public function handle(): int
    {
        $urgent = $this->option('urgent');
        $dryRun = $this->option('dry-run');
        $now    = now();

        // ── 1. Get active campaigns ───────────────────────────────────────────
        $query = DiscountCampaign::with(['vendor:id,name,logo', 'category:id,name'])
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at',   '>=', $now)
            ->whereHas('vendor', fn($q) => $q->where('module_slug', 'efood'));

        if ($urgent) {
            // Urgent mode: only campaigns ending in the next 2 hours
            $query->where('ends_at', '<=', $now->copy()->addHours(2));
        }

        $campaigns = $query->get();

        if ($campaigns->isEmpty()) {
            $mode = $urgent ? 'urgent' : 'normal';
            $this->info("No active eFood campaigns found [{$mode} mode].");
            return 0;
        }

        $mode = $urgent ? '⚡ URGENT' : '🔔 Normal';
        $this->info("{$mode} | Found {$campaigns->count()} campaign(s).");

        // ── 2. All users with FCM tokens ─────────────────────────────────────
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
            // Skip if admin paused notifications for this campaign
            if ($campaign->notif_paused) {
                $this->info("  ⏸ Campaign #{$campaign->id} ({$campaign->vendor?->name}) — PAUSED, skipping.");
                continue;
            }

            $vendorId   = $campaign->vendor_id;
            $vendorName = $campaign->vendor?->name ?? 'Restaurant';
            $logoUrl    = $campaign->vendor?->logo;
            $catName    = $campaign->category?->name;
            $endsAt     = $campaign->ends_at;

            // Per-campaign cache TTL: use custom interval or default
            $intervalHours = ($campaign->notif_interval_hours && $campaign->notif_interval_hours > 0)
                ? (int) $campaign->notif_interval_hours
                : ($urgent ? 0 : 2);
            $cacheTtl = $urgent
                ? self::URGENT_TTL
                : max(1800, $intervalHours * 3600); // min 30min

            // Discount label
            $discountStr = $campaign->discount_type === 'percentage'
                ? "{$campaign->discount_value}% OFF"
                : '$' . number_format($campaign->discount_value, 0) . ' OFF';

            // Time remaining label
            $minutesLeft = (int) $now->diffInMinutes($endsAt, false);
            $timeLabel   = $minutesLeft <= 60
                ? $minutesLeft . ' minutes left!'
                : ($endsAt->diffInHours($now) . 'h left — ends ' . $endsAt->format('g:ia'));

            // ── 3. Users who already ordered during this campaign ─────────────
            $purchasedUserIds = Order::where('vendor_id', $vendorId)
                ->where('module_slug', 'efood')
                ->whereBetween('created_at', [$campaign->starts_at, $endsAt])
                ->whereNotIn('status', ['cancelled', 'rejected', 'failed'])
                ->pluck('user_id')
                ->unique()
                ->flip();

            // ── 4. Build notification content (custom or auto-generated) ──────
            // Use admin-defined custom text if set, otherwise auto-generate
            if (!empty($campaign->notif_title)) {
                $title = $campaign->notif_title;
                $body  = $campaign->notif_body ?? '';
            } elseif ($urgent) {
                $title = "⏰ Hurry! {$discountStr} at {$vendorName} — {$timeLabel}";
                $body  = $catName
                    ? "Last chance! {$discountStr} on {$catName} at {$vendorName}. Don't miss it!"
                    : "Last chance! {$discountStr} at {$vendorName}. Offer ends soon!";
            } else {
                $title = "🔥 {$discountStr} at {$vendorName}!";
                $body  = $catName
                    ? "Get {$discountStr} on {$catName} at {$vendorName}. Ends {$endsAt->format('M j, g:ia')}!"
                    : "Get {$discountStr} at {$vendorName}. Ends {$endsAt->format('M j, g:ia')}!";
            }

            // Deep link → Flutter reads data['deep_link'] and calls router.push()
            // Route: /vendor/:id  (GoRouter path in app_router.dart)
            $deepLink = '/efood/restaurant/' . $vendorId;

            $data = [
                'type'        => 'discount_campaign',
                'campaign_id' => (string) $campaign->id,
                'vendor_id'   => (string) $vendorId,
                'module'      => 'efood',
                'deep_link'   => $deepLink,   // ← Flutter navigates here on tap
                'is_urgent'   => $urgent ? '1' : '0',
            ];

            $sent = 0;

            foreach ($users as $userId => $user) {
                // Skip users who purchased during campaign
                if (isset($purchasedUserIds[$userId])) continue;

                // Cache key — urgent and normal use different keys so they don't block each other
                $prefix   = $urgent ? 'camp_notif_urgent' : 'camp_notif';
                $cacheKey = "{$prefix}:{$campaign->id}:{$userId}";
                if (Cache::has($cacheKey)) continue;

                if ($dryRun) {
                    $this->line("  [DRY] → user #{$userId} | {$vendorName} | link: {$deepLink}");
                } else {
                    $ok = FcmService::sendToToken(
                        fcmToken:  $user->fcm_token,
                        title:     $title,
                        body:      $body,
                        data:      $data,
                        imageUrl:  $logoUrl,
                        channelId: $urgent ? 'esahlan_high_v3' : 'esahlan_promo',
                    );

                    if ($ok) {
                        Cache::put($cacheKey, 1, $cacheTtl);
                        $sent++;
                    }
                }
            }

            $totalSent += $sent;
            $label = $urgent ? '⚡ Urgent' : '🔔';
            $this->info("  {$label} Campaign #{$campaign->id} ({$vendorName}): sent {$sent} notifications" . ($dryRun ? ' [DRY]' : ''));
            Log::info("[CampaignNotif] campaign={$campaign->id} vendor={$vendorName} sent={$sent} urgent=" . ($urgent ? 'yes' : 'no'));
        }

        $this->info("Done. Total sent: {$totalSent}");
        return 0;
    }
}
