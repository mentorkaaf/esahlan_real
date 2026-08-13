<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\FcmService;
use Carbon\Carbon;

/**
 * Sends time-based promotional push notifications.
 *
 * Templates:
 *  - lunch_time   → 11:30 AM daily
 *  - evening_deals → 6:00 PM daily
 *  - weekend_promo → Friday 10:00 AM
 *
 * Runs every 30 minutes via scheduler. Each template checks internally
 * whether the current time matches its send_time window (±5 minutes).
 * Use --slug to force-send a specific template regardless of time.
 *
 * Admin can enable/disable each template from Auto Notifications panel.
 */
class SendTimeBasedNotifications extends Command
{
    protected $signature = 'marketing:time-based
                            {--slug=   : Force-send a specific slug regardless of time check}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send time-based promotional push notifications (lunch, evening, weekend)';

    /** Slug → time-check callable */
    private function shouldSend(string $slug, Carbon $now): bool
    {
        return match($slug) {
            'lunch_time'    => $now->hour === 11 && $now->minute >= 25 && $now->minute <= 35,
            'evening_deals' => $now->hour === 17 && $now->minute >= 55
                            || $now->hour === 18 && $now->minute <= 5,
            'weekend_promo' => $now->dayOfWeek === Carbon::FRIDAY
                            && $now->hour === 10 && $now->minute <= 30,
            default => false,
        };
    }

    public function handle(): int
    {
        $dryRun    = $this->option('dry-run');
        $slugForce = $this->option('slug');
        $now       = now();

        $slugs = ['lunch_time', 'evening_deals', 'weekend_promo'];

        // If specific slug forced, only process that one
        if ($slugForce) {
            $slugs = in_array($slugForce, $slugs) ? [$slugForce] : [];
            if (empty($slugs)) {
                $this->error("Unknown slug: {$slugForce}");
                return 1;
            }
        }

        $totalSent = 0;

        foreach ($slugs as $slug) {
            // Skip if not forced and time window doesn't match
            if (!$slugForce && !$this->shouldSend($slug, $now)) {
                $this->line("  [{$slug}] Not in time window — skipping.");
                continue;
            }

            $sent = $this->processSlug($slug, $dryRun);
            $totalSent += $sent;
        }

        $this->info("Done. Total sent: {$totalSent}" . ($dryRun ? ' [DRY RUN]' : ''));
        Log::info("[TimeBasedNotif] now={$now->toDateTimeString()} total_sent={$totalSent}");
        return 0;
    }

    private function processSlug(string $slug, bool $dryRun): int
    {
        // ── Load template ────────────────────────────────────────────────────
        $template = DB::table('auto_notification_templates')->where('slug', $slug)->first();

        if ($template && !$template->is_active) {
            $this->line("  [{$slug}] DISABLED by admin — skipping.");
            return 0;
        }

        $intervalHours = $template?->interval_hours ?? 24;
        $cacheTtl      = $intervalHours * 3600;
        $language      = $template?->language ?? 'en';

        if ($language === 'so' && $template?->title_so) {
            $title = $template->title_so;
            $body  = $template->body_so ?? $template->body_template;
        } else {
            $title = $template?->title_template ?? 'Special offer from eSahlan!';
            $body  = $template?->body_template  ?? 'Check out today\'s deals!';
        }

        // ── All users with FCM ────────────────────────────────────────────────
        $users = User::whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('status', '!=', 'banned')
            ->select('id', 'fcm_token')
            ->get();

        if ($users->isEmpty()) {
            $this->line("  [{$slug}] No users with FCM tokens.");
            return 0;
        }

        $this->info("  [{$slug}] Sending to up to {$users->count()} users…");

        $data = [
            'type'      => 'time_based_promo',
            'slug'      => $slug,
            'module'    => 'home',
            'deep_link' => '/home',
        ];

        $sent = 0;
        foreach ($users as $user) {
            $cacheKey = "timebased_{$slug}:{$user->id}";
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
                    'reference_type' => 'time_based',
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
