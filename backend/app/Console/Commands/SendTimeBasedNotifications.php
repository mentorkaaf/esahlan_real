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
 * Timing is read from auto_notification_templates.send_time column (HH:MM).
 * weekend_promo also checks day-of-week via the 'target_audience' column
 * (value "friday" fires only on Fridays, otherwise daily).
 *
 * Runs every 30 minutes. Each template checks if current time is within
 * ±15 minutes of its send_time. Admin can change time from the panel — live.
 */
class SendTimeBasedNotifications extends Command
{
    protected $signature = 'marketing:time-based
                            {--slug=   : Force-send a specific slug regardless of time check}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send time-based promotional push notifications (lunch, evening, weekend)';

    /** Read send_time from DB and check if now is within ±15 min window */
    private function shouldSend(object $template, Carbon $now): bool
    {
        $sendTime = $template->send_time ?? null;

        // Fallback defaults if admin never set a time
        if (!$sendTime) {
            $sendTime = match($template->slug) {
                'lunch_time'    => '11:30',
                'evening_deals' => '18:00',
                'weekend_promo' => '10:00',
                default         => null,
            };
        }

        if (!$sendTime) return false;

        [$h, $m] = array_map('intval', explode(':', $sendTime));
        $target  = $now->copy()->setTime($h, $m, 0);
        $diff    = abs($now->diffInMinutes($target, false));

        // Must be within ±15 minutes of the configured time
        if ($diff > 15) return false;

        // weekend_promo: only fire on Friday (or as configured via target_audience)
        if ($template->slug === 'weekend_promo') {
            $audience = strtolower($template->target_audience ?? 'friday');
            if (str_contains($audience, 'friday') && $now->dayOfWeek !== Carbon::FRIDAY) {
                return false;
            }
        }

        return true;
    }

    public function handle(): int
    {
        $dryRun    = $this->option('dry-run');
        $slugForce = $this->option('slug');
        $now       = now();

        $slugs = ['lunch_time', 'evening_deals', 'weekend_promo'];

        if ($slugForce) {
            $slugs = in_array($slugForce, $slugs) ? [$slugForce] : [];
            if (empty($slugs)) {
                $this->error("Unknown slug: {$slugForce}");
                return 1;
            }
        }

        $totalSent = 0;

        foreach ($slugs as $slug) {
            // Load template to read send_time from DB
            $template = DB::table('auto_notification_templates')->where('slug', $slug)->first();

            if (!$slugForce && !$this->shouldSend($template, $now)) {
                $time = $template?->send_time ?? 'not set';
                $this->line("  [{$slug}] Not in time window (send_time={$time}) — skipping.");
                continue;
            }

            $sent = $this->processSlug($slug, $template, $dryRun);
            $totalSent += $sent;
        }

        $this->info("Done. Total sent: {$totalSent}" . ($dryRun ? ' [DRY RUN]' : ''));
        Log::info("[TimeBasedNotif] now={$now->toDateTimeString()} total_sent={$totalSent}");
        return 0;
    }

    private function processSlug(string $slug, ?object $template, bool $dryRun): int
    {
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
            $body  = $template?->body_template  ?? "Check out today's deals!";
        }

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
                $this->line("    [DRY] user#{$user->id} | {$title}");
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
