<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Services\FcmService;

/**
 * Sends loyalty-related push notifications.
 *
 * Types:
 *  - points_expiry → users with reward points expiring in 3 days
 *  - wallet_low    → users with ePay wallet balance < 5.00
 *
 * Gracefully skips if the target table doesn't exist.
 * Cache prevents duplicate sends within interval_hours per user.
 *
 * Runs daily at 09:00 via scheduler. Use --type to run a single type.
 */
class SendLoyaltyNotifications extends Command
{
    protected $signature = 'marketing:loyalty
                            {--type=   : Run only one type: points_expiry or wallet_low}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send loyalty push notifications (reward points expiry, low wallet balance)';

    private const LOW_WALLET_THRESHOLD = 5.00;

    /**
     * Check if it's time to send based on template's send_time (admin-configurable).
     * If send_time is set: fire only within ±15 min of that time, once per day.
     * If send_time is NOT set: fire once per day (guard via last_sent_at).
     */
    private function shouldRunNow(?object $template, \Carbon\Carbon $now): bool
    {
        if (!$template) return false;

        $sendTime = $template->send_time ?? null;

        if ($sendTime) {
            // Admin set a specific time — check ±15 min window
            [$h, $m] = array_map('intval', explode(':', $sendTime));
            $target  = $now->copy()->setTime($h, $m, 0);
            if (abs($now->diffInMinutes($target, false)) > 15) return false;

            // Within window — guard against double-fire on same day
            $firedKey = "loyalty_{$template->slug}_fired:" . $now->toDateString();
            if (Cache::has($firedKey)) return false;
            Cache::put($firedKey, 1, 23 * 3600);
            return true;
        }

        // No send_time: fall back to once-daily guard via last_sent_at
        if ($template->last_sent_at) {
            $lastSent = \Carbon\Carbon::parse($template->last_sent_at);
            if ($lastSent->isToday()) return false;
        }
        return true;
    }

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $typeOpt = $this->option('type');
        $now     = now();

        $types = ['points_expiry', 'wallet_low'];
        if ($typeOpt) {
            $types = in_array($typeOpt, $types) ? [$typeOpt] : [];
            if (empty($types)) {
                $this->error("Unknown --type. Allowed: points_expiry, wallet_low");
                return 1;
            }
        }

        $totalSent = 0;
        foreach ($types as $type) {
            $slug     = $type === 'points_expiry' ? 'points_expiry' : 'wallet_low';
            $template = DB::table('auto_notification_templates')->where('slug', $slug)->first();

            if (!$dryRun && !$typeOpt && !$this->shouldRunNow($template, $now)) {
                $time = $template?->send_time ?? 'no time set';
                $this->line("  [{$slug}] Not in send window ({$time}) — skipping.");
                continue;
            }

            $sent = match($type) {
                'points_expiry' => $this->sendPointsExpiry($dryRun),
                'wallet_low'    => $this->sendWalletLow($dryRun),
                default         => 0,
            };
            $totalSent += $sent;
        }

        $this->info("Done. Total sent: {$totalSent}" . ($dryRun ? ' [DRY RUN]' : ''));
        Log::info("[LoyaltyNotif] types=" . implode(',', $types) . " total_sent={$totalSent}");
        return 0;
    }

    // ── Points Expiry ─────────────────────────────────────────────────────────

    private function sendPointsExpiry(bool $dryRun): int
    {
        $slug     = 'points_expiry';
        $template = DB::table('auto_notification_templates')->where('slug', $slug)->first();

        if ($template && !$template->is_active) {
            $this->line("  [{$slug}] DISABLED by admin — skipping.");
            return 0;
        }

        $intervalHours = $template?->interval_hours ?? 24;
        $cacheTtl      = $intervalHours * 3600;
        $language      = $template?->language ?? 'en';

        $titleEnTpl = $template?->title_template ?? '⏳ Your points expire soon!';
        $bodyEnTpl  = $template?->body_template  ?? 'You have {points} reward points expiring in 3 days. Use them before they\'re gone!';
        $titleSoTpl = $template?->title_so ?: null;
        $bodySoTpl  = $template?->body_so  ?: null;

        if ($language === 'so' && $titleSoTpl) {
            $titleTpl = $titleSoTpl;
            $bodyTpl  = $bodySoTpl ?? $bodyEnTpl;
        } elseif ($language === 'both' && $titleSoTpl) {
            $titleTpl = $titleEnTpl . ' | ' . $titleSoTpl;
            $bodyTpl  = $bodyEnTpl . "\n\n" . ($bodySoTpl ?? '');
        } else {
            $titleTpl = $titleEnTpl;
            $bodyTpl  = $bodyEnTpl;
        }

        // ── Query reward points table ─────────────────────────────────────────
        $pointsTable = null;
        foreach (['user_points', 'reward_points', 'loyalty_points'] as $candidate) {
            try {
                if (Schema::hasTable($candidate)) {
                    $pointsTable = $candidate;
                    break;
                }
            } catch (\Throwable) {}
        }

        if (!$pointsTable) {
            $this->line("  [{$slug}] No reward points table found (user_points / reward_points / loyalty_points) — skipping.");
            Log::info("[LoyaltyNotif] No points table found — points_expiry skipped.");
            return 0;
        }

        // Look for points expiring in 3 days
        $expiryCutoff = now()->addDays(3);

        try {
            $rows = DB::table($pointsTable)
                ->whereNotNull('user_id')
                ->where('points', '>', 0)
                ->where('expires_at', '<=', $expiryCutoff)
                ->where('expires_at', '>=', now())
                ->select('user_id', DB::raw('SUM(points) as total_points'))
                ->groupBy('user_id')
                ->get();
        } catch (\Throwable $e) {
            Log::warning("[LoyaltyNotif] Could not query {$pointsTable}: " . $e->getMessage());
            $this->line("  [{$slug}] Failed to query {$pointsTable}: " . $e->getMessage());
            return 0;
        }

        if ($rows->isEmpty()) {
            $this->line("  [{$slug}] No users with expiring points found.");
            return 0;
        }

        $this->info("  [{$slug}] Found {$rows->count()} users with expiring points.");

        $userIds = $rows->pluck('total_points', 'user_id');
        $users   = User::whereIn('id', $userIds->keys())
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('status', '!=', 'banned')
            ->select('id', 'fcm_token')
            ->get();

        $sent = 0;
        foreach ($users as $user) {
            $cacheKey = "loyalty_points_expiry:{$user->id}";
            if (Cache::has($cacheKey)) continue;

            $points = (int) ($userIds[$user->id] ?? 0);
            $title  = str_replace('{points}', $points, $titleTpl);
            $body   = str_replace('{points}', $points, $bodyTpl);
            $data   = [
                'type'      => 'points_expiry',
                'module'    => 'wallet',
                'deep_link' => '/wallet/points',
            ];

            if ($dryRun) {
                $this->line("    [DRY] → user#{$user->id} | {$title}");
            } else {
                $ok = FcmService::sendToToken(
                    fcmToken:  $user->fcm_token,
                    title:     $title,
                    body:      $body,
                    data:      $data,
                    imageUrl:  null,
                    channelId: $template?->channel_id ?? 'esahlan_promo',
                );
                if ($ok) {
                    Cache::put($cacheKey, 1, $cacheTtl);
                    $sent++;
                }
            }
        }

        $this->info("  [{$slug}] Sent: {$sent}" . ($dryRun ? ' [DRY]' : ''));

        if (!$dryRun) {
            if ($sent > 0) {
                DB::table('auto_notification_logs')->insert([
                    'template_slug'  => $slug,
                    'reference_id'   => null,
                    'reference_type' => 'loyalty',
                    'title'          => $titleTpl,
                    'body'           => $bodyTpl,
                    'sent_count'     => $sent,
                    'created_at'     => now(),
                ]);
            }
            DB::table('auto_notification_templates')
                ->where('slug', $slug)
                ->update(['last_sent_at' => now()]);
        }

        return $sent;
    }

    // ── Wallet Low ────────────────────────────────────────────────────────────

    private function sendWalletLow(bool $dryRun): int
    {
        $slug     = 'wallet_low';
        $template = DB::table('auto_notification_templates')->where('slug', $slug)->first();

        if ($template && !$template->is_active) {
            $this->line("  [{$slug}] DISABLED by admin — skipping.");
            return 0;
        }

        $intervalHours = $template?->interval_hours ?? 72;
        $cacheTtl      = $intervalHours * 3600;
        $language      = $template?->language ?? 'en';

        $titleEn = $template?->title_template ?? '💳 Top up your ePay wallet!';
        $bodyEn  = $template?->body_template  ?? 'Your ePay balance is running low. Add funds for faster checkout!';
        $titleSo = $template?->title_so ?: null;
        $bodySo  = $template?->body_so  ?: null;

        if ($language === 'so' && $titleSo) {
            $title = $titleSo;
            $body  = $bodySo ?? $bodyEn;
        } elseif ($language === 'both' && $titleSo) {
            $title = $titleEn . ' | ' . $titleSo;
            $body  = $bodyEn . "\n\n" . ($bodySo ?? '');
        } else {
            $title = $titleEn;
            $body  = $bodyEn;
        }

        // ── Check epay_wallets table ──────────────────────────────────────────
        $walletTable = null;
        foreach (['epay_wallets', 'e_pay_wallets', 'wallets'] as $candidate) {
            try {
                if (Schema::hasTable($candidate)) {
                    $walletTable = $candidate;
                    break;
                }
            } catch (\Throwable) {}
        }

        if (!$walletTable) {
            $this->line("  [{$slug}] No wallet table found (epay_wallets / wallets) — skipping.");
            Log::info("[LoyaltyNotif] No wallet table found — wallet_low skipped.");
            return 0;
        }

        try {
            // wallets is polymorphic (owner_type + owner_id) — check which column exists
            $hasUserId = Schema::hasColumn($walletTable, 'user_id');
            $query = DB::table($walletTable)
                ->where('balance', '<', self::LOW_WALLET_THRESHOLD)
                ->where('balance', '>=', 0);
            if ($hasUserId) {
                $query->whereNotNull('user_id');
                $walletUserIds = $query->pluck('user_id')->filter()->unique()->values()->toArray();
            } else {
                $query->where('owner_type', \App\Models\User::class)->whereNotNull('owner_id');
                $walletUserIds = $query->pluck('owner_id')->filter()->unique()->values()->toArray();
            }
        } catch (\Throwable $e) {
            Log::warning("[LoyaltyNotif] Could not query {$walletTable}: " . $e->getMessage());
            $this->line("  [{$slug}] Failed to query {$walletTable}: " . $e->getMessage());
            return 0;
        }

        if (empty($walletUserIds)) {
            $this->line("  [{$slug}] No users with low wallet balance found.");
            return 0;
        }

        $users = User::whereIn('id', $walletUserIds)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('status', '!=', 'banned')
            ->select('id', 'fcm_token')
            ->get();

        if ($users->isEmpty()) {
            $this->line("  [{$slug}] No users with FCM tokens in low-balance group.");
            return 0;
        }

        $this->info("  [{$slug}] Found {$users->count()} users with low wallet balance.");

        $data = [
            'type'      => 'wallet_low',
            'module'    => 'wallet',
            'deep_link' => '/wallet/topup',
        ];

        $sent = 0;
        foreach ($users as $user) {
            $cacheKey = "loyalty_wallet_low:{$user->id}";
            if (Cache::has($cacheKey)) continue;

            if ($dryRun) {
                $this->line("    [DRY] → user#{$user->id} | {$title}");
            } else {
                $ok = FcmService::sendToToken(
                    fcmToken:  $user->fcm_token,
                    title:     $title,
                    body:      $body,
                    data:      $data,
                    imageUrl:  null,
                    channelId: $template?->channel_id ?? 'esahlan_promo',
                );
                if ($ok) {
                    Cache::put($cacheKey, 1, $cacheTtl);
                    $sent++;
                }
            }
        }

        $this->info("  [{$slug}] Sent: {$sent}" . ($dryRun ? ' [DRY]' : ''));

        if (!$dryRun) {
            if ($sent > 0) {
                DB::table('auto_notification_logs')->insert([
                    'template_slug'  => $slug,
                    'reference_id'   => null,
                    'reference_type' => 'loyalty',
                    'title'          => $title,
                    'body'           => $body,
                    'sent_count'     => $sent,
                    'created_at'     => now(),
                ]);
            }
            DB::table('auto_notification_templates')
                ->where('slug', $slug)
                ->update(['last_sent_at' => now()]);
        }

        return $sent;
    }
}
