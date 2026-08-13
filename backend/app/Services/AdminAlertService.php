<?php

namespace App\Services;

use App\Mail\AdminAlertMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * AdminAlertService — Central service for all admin email notifications.
 *
 * Usage:
 *   AdminAlertService::send('new_order', 'New Order #123', [...data...]);
 *
 * The service:
 *  - Checks the alert is enabled in admin_alert_settings
 *  - Reads admin email from settings table (key: admin_alert_email)
 *  - Queues the email (non-blocking)
 *  - Logs to admin_alert_logs
 *  - Rate-limits noisy alerts (e.g. new_customer) with Redis cache
 */
class AdminAlertService
{
    /** Send an admin alert email. Non-blocking — queued. */
    public static function send(string $key, string $subject, array $data = [], ?string $rateKey = null, int $rateTtl = 0): void
    {
        try {
            // Rate limiting — skip if same alert was sent recently
            if ($rateKey && $rateTtl > 0) {
                if (Cache::has("admin_alert_rate:{$rateKey}")) return;
                Cache::put("admin_alert_rate:{$rateKey}", 1, $rateTtl);
            }

            // Check if this alert type is enabled
            $setting = DB::table('admin_alert_settings')->where('key', $key)->first();
            if (!$setting || !$setting->is_enabled) return;

            // Get admin email(s)
            $adminEmail = DB::table('settings')->where('key', 'admin_alert_email')->value('value')
                ?? DB::table('settings')->where('key', 'app_email')->value('value')
                ?? config('mail.from.address');

            if (!$adminEmail) return;

            // Queue the email
            $emails = array_map('trim', explode(',', $adminEmail));
            foreach ($emails as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                Mail::to($email)->queue(new AdminAlertMail($key, $subject, $data, $setting->label ?? $key));
            }

            // Log it
            DB::table('admin_alert_logs')->insert([
                'alert_key' => $key,
                'subject'   => $subject,
                'to_email'  => $adminEmail,
                'status'    => 'sent',
                'sent_at'   => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error("AdminAlertService: failed to send alert [{$key}] — " . $e->getMessage());
            try {
                DB::table('admin_alert_logs')->insert([
                    'alert_key' => $key,
                    'subject'   => $subject,
                    'to_email'  => 'unknown',
                    'status'    => 'failed',
                    'error'     => $e->getMessage(),
                    'sent_at'   => now(),
                ]);
            } catch (\Throwable) {}
        }
    }
}
