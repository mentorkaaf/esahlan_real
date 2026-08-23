<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\FcmService;

/**
 * Sends re-engagement push notifications to inactive users.
 *
 * Targets users who haven't placed an order in 3, 7, 14, or 30 days.
 * Uses Redis cache to prevent duplicate sends within the template's interval.
 * Admin can configure templates via Admin → Notifications → Auto Notifications.
 *
 * Schedules:
 *  - marketing:reengagement        → all 4 periods, daily
 *  - marketing:reengagement --days=3  → only 3-day period
 *  - marketing:reengagement --dry-run → preview without sending
 */
class SendReengagementNotifications extends Command
{
    protected $signature = 'marketing:reengagement
                            {--days=  : Target specific inactivity period (3, 7, 14, 30). Omit to run all.}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send re-engagement FCM push notifications to inactive users (3/7/14/30 days)';

    /** Inactivity period definitions */
    private const PERIODS = [
        3  => 'reengagement_3d',
        7  => 'reengagement_7d',
        14 => 'reengagement_14d',
        30 => 'reengagement_30d',
    ];

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
            [$h, $m] = array_map('intval', explode(':', $sendTime));
            $target  = $now->copy()->setTime($h, $m, 0);
            if (abs($now->diffInMinutes($target, false)) > 15) return false;

            $firedKey = "reeng_{$template->slug}_fired:" . $now->toDateString();
            if (Cache::has($firedKey)) return false;
            Cache::put($firedKey, 1, 23 * 3600);
            return true;
        }

        // No send_time: once per day via last_sent_at
        if ($template->last_sent_at) {
            $lastSent = \Carbon\Carbon::parse($template->last_sent_at);
            if ($lastSent->isToday()) return false;
        }
        return true;
    }

    public function handle(): int
    {
        $dryRun     = $this->option('dry-run');
        $daysFilter = $this->option('days') ? (int) $this->option('days') : null;
        $now        = now();

        $periods = $daysFilter
            ? (isset(self::PERIODS[$daysFilter]) ? [$daysFilter => self::PERIODS[$daysFilter]] : [])
            : self::PERIODS;

        if (empty($periods)) {
            $this->error("Invalid --days value. Allowed: 3, 7, 14, 30.");
            return 1;
        }

        $totalSent = 0;

        foreach ($periods as $days => $slug) {
            if (!$dryRun && !$daysFilter) {
                $template = DB::table('auto_notification_templates')->where('slug', $slug)->first();
                if (!$this->shouldRunNow($template, $now)) {
                    $time = $template?->send_time ?? 'no time set';
                    $this->line("  [{$slug}] Not in send window ({$time}) — skipping.");
                    continue;
                }
            }
            $sent = $this->processPeriod($days, $slug, $dryRun);
            $totalSent += $sent;
        }

        $this->info("Done. Total notifications sent: {$totalSent}" . ($dryRun ? ' [DRY RUN]' : ''));
        Log::info("[Reengagement] periods=" . implode(',', array_keys($periods)) . " total_sent={$totalSent}");
        return 0;
    }

    private function processPeriod(int $days, string $slug, bool $dryRun): int
    {
        // ── Load template ────────────────────────────────────────────────────
        $template = DB::table('auto_notification_templates')->where('slug', $slug)->first();

        if ($template && !$template->is_active) {
            $this->line("  [{$slug}] DISABLED by admin — skipping.");
            return 0;
        }

        $intervalHours = $template?->interval_hours ?? ($days * 24);
        $cacheTtl      = $intervalHours * 3600;
        $language      = $template?->language ?? 'en';

        // Pick title/body based on language setting
        $titleEn = $template?->title_template ?? "We miss you! It's been {$days} days.";
        $bodyEn  = $template?->body_template  ?? "Come back to eSahlan and see what's new!";
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

        // ── Find inactive users ───────────────────────────────────────────────
        // "Inactive" = last order was between (days) and (days+1) days ago
        $cutoffEnd   = now()->subDays($days);
        $cutoffStart = now()->subDays($days + 1);

        try {
            // Users whose last order was in the [days, days+1) window
            $userIds = DB::table('orders')
                ->select('user_id', DB::raw('MAX(created_at) as last_order'))
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->havingRaw('MAX(created_at) <= ?', [$cutoffEnd])
                ->havingRaw('MAX(created_at) >= ?', [$cutoffStart])
                ->pluck('user_id')
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning("[Reengagement:{$slug}] Could not query orders table: " . $e->getMessage());
            $userIds = [];
        }

        if (empty($userIds)) {
            $this->line("  [{$slug}] No inactive users found for {$days}-day window.");
            return 0;
        }

        // Get FCM tokens for these users
        $users = User::whereIn('id', $userIds)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('status', '!=', 'banned')
            ->select('id', 'fcm_token')
            ->get();

        if ($users->isEmpty()) {
            $this->line("  [{$slug}] No users with FCM tokens in {$days}-day inactive group.");
            return 0;
        }

        $this->info("  [{$slug}] Found {$users->count()} inactive users ({$days}d window).");

        $data = [
            'type'      => 'reengagement',
            'slug'      => $slug,
            'module'    => 'home',
            'deep_link' => '/home',
        ];

        $sent = 0;
        foreach ($users as $user) {
            $cacheKey = "reeng_{$slug}:{$user->id}";
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
                    'reference_type' => 'reengagement',
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
