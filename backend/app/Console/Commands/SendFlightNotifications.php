<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\FcmService;

/**
 * Sends FCM push notifications for upcoming scheduled flights.
 *
 * Runs every 12 hours via scheduler (sends twice per day).
 * Uses Redis cache to prevent duplicate sends within the same window.
 * Deep link → /eticket/flight/:id (opens passenger selection directly).
 *
 * Admin can:
 *  - Enable/disable per notification template (auto_notification_templates table)
 *  - Edit title/body template with {from}, {to}, {date}, {price}, {seats} variables
 *  - Change interval_hours (default 12)
 *  - Trigger manual send from admin panel
 */
class SendFlightNotifications extends Command
{
    protected $signature = 'eticket:send-flight-notifications
                            {--dry-run  : Show what would be sent without sending}
                            {--flight=  : Send for specific flight ID only}';

    protected $description = 'Send FCM push notifications for upcoming scheduled flights (runs every 12h)';

    // Cache TTL = interval between sends (12h by default, respects template setting)
    private const DEFAULT_INTERVAL_HOURS = 12;

    public function handle(): int
    {
        $dryRun   = $this->option('dry-run');
        $flightId = $this->option('flight');
        $now      = now();

        // ── Load template config from admin settings ───────────────────────────
        $template = DB::table('auto_notification_templates')
            ->where('slug', 'eticket_upcoming_flight')
            ->first();

        // If template exists and is disabled, stop
        if ($template && !$template->is_active) {
            $this->info('eTicket flight notifications are DISABLED by admin.');
            return 0;
        }

        $intervalHours = $template?->interval_hours ?? self::DEFAULT_INTERVAL_HOURS;
        $cacheTtl      = $intervalHours * 3600;

        $titleTpl = $template?->title_template
            ?? '✈️ {from} → {to} — Available Now!';
        $bodyTpl  = $template?->body_template
            ?? 'Flight {flight_no} on {date} · {seats} seats from ${price}. Book your seat now!';

        // ── Get upcoming scheduled flights ────────────────────────────────────
        $query = DB::table('flights as f')
            ->leftJoin('airlines as a', 'a.id', '=', 'f.airline_id')
            ->where('f.status', 'scheduled')
            ->where('f.available_seats', '>', 0)
            ->whereDate('f.departure_at', '>=', $now->toDateString())
            ->whereDate('f.departure_at', '<=', $now->copy()->addDays(30)->toDateString())
            ->select(
                'f.id', 'f.flight_number', 'f.from_city', 'f.to_city',
                'f.from_code', 'f.to_code', 'f.departure_at', 'f.available_seats',
                'f.seat_classes', 'a.name as airline_name', 'a.logo as airline_logo'
            )
            ->orderBy('f.departure_at');

        if ($flightId) {
            $query->where('f.id', $flightId);
        }

        $flights = $query->get();

        if ($flights->isEmpty()) {
            $this->info('No upcoming flights found to notify about.');
            return 0;
        }

        $this->info("Found {$flights->count()} upcoming flight(s).");

        // ── Users with FCM tokens ──────────────────────────────────────────────
        $users = User::whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('status', '!=', 'banned')
            ->select('id', 'fcm_token')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users with FCM tokens.');
            return 0;
        }

        $totalSent = 0;

        foreach ($flights as $flight) {
            // Parse seat classes for economy price
            $classes = [];
            try { $classes = json_decode($flight->seat_classes ?? '{}', true) ?? []; } catch (\Throwable) {}
            $ecoPrice = 0;
            foreach (['economy', 'Economy'] as $k) {
                if (isset($classes[$k])) {
                    $ecoPrice = (float) $classes[$k];
                    break;
                }
            }
            if ($ecoPrice <= 0) $ecoPrice = array_values($classes)[0] ?? 0;

            // Format date
            $depDate = 'N/A';
            try { $depDate = \Carbon\Carbon::parse($flight->departure_at)->format('M j, Y'); } catch (\Throwable) {}

            // Build notification content from template
            $replace = [
                '{from}'      => $flight->from_city  ?? $flight->from_code ?? '?',
                '{to}'        => $flight->to_city    ?? $flight->to_code   ?? '?',
                '{from_code}' => strtoupper($flight->from_code ?? '?'),
                '{to_code}'   => strtoupper($flight->to_code   ?? '?'),
                '{flight_no}' => $flight->flight_number ?? '',
                '{date}'      => $depDate,
                '{price}'     => $ecoPrice > 0 ? number_format($ecoPrice, 0) : '?',
                '{seats}'     => $flight->available_seats,
                '{airline}'   => $flight->airline_name ?? '',
            ];

            $title = str_replace(array_keys($replace), array_values($replace), $titleTpl);
            $body  = str_replace(array_keys($replace), array_values($replace), $bodyTpl);

            $deepLink = '/eticket/flight/' . $flight->id;
            $data = [
                'type'      => 'eticket_upcoming_flight',
                'flight_id' => (string) $flight->id,
                'module'    => 'eticket',
                'deep_link' => $deepLink,
            ];

            $sent = 0;
            foreach ($users as $user) {
                $cacheKey = "flight_notif:{$flight->id}:{$user->id}";
                if (Cache::has($cacheKey)) continue;

                if ($dryRun) {
                    $this->line("  [DRY] → user#{$user->id} | {$title} | link:{$deepLink}");
                } else {
                    $ok = FcmService::sendToToken(
                        fcmToken:  $user->fcm_token,
                        title:     $title,
                        body:      $body,
                        data:      $data,
                        imageUrl:  $flight->airline_logo ?? null,
                        channelId: 'esahlan_promo',
                    );
                    if ($ok) {
                        Cache::put($cacheKey, 1, $cacheTtl);
                        $sent++;
                    }
                }
            }

            $totalSent += $sent;
            $label = "{$flight->from_code}→{$flight->to_code} ({$flight->flight_number})";
            $this->info("  ✈️  Flight #{$flight->id} {$label}: sent {$sent}" . ($dryRun ? ' [DRY]' : ''));

            // Log to auto_notification_logs
            if (!$dryRun && $sent > 0) {
                DB::table('auto_notification_logs')->insert([
                    'template_slug' => 'eticket_upcoming_flight',
                    'reference_id'  => $flight->id,
                    'reference_type'=> 'flight',
                    'title'         => $title,
                    'body'          => $body,
                    'sent_count'    => $sent,
                    'created_at'    => now(),
                ]);
            }
        }

        if (!$dryRun) {
            DB::table('auto_notification_templates')
                ->where('slug', 'eticket_upcoming_flight')
                ->update(['last_sent_at' => now()]);
        }

        $this->info("Done. Total sent: {$totalSent}");
        Log::info("[FlightNotif] flights={$flights->count()} users={$users->count()} sent={$totalSent}");
        return 0;
    }
}
