<?php

namespace App\Console\Commands;

use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ValidateFcmTokens — Daily command to keep FCM tokens healthy.
 *
 * What it does:
 *  1. Sends a silent "data-only" test ping to each unique FCM token.
 *  2. Invalid tokens (403/404 from FCM) are auto-cleared by FcmService.
 *  3. Users with null tokens who haven't opened the app in > 60 days are logged.
 *
 * Run: php artisan fcm:validate-tokens
 * Schedule: daily at 03:00
 */
class ValidateFcmTokens extends Command
{
    protected $signature   = 'fcm:validate-tokens {--dry-run : Show what would be cleared without sending}';
    protected $description = 'Validate all FCM tokens and clear invalid ones';

    public function handle(): int
    {
        $this->info('[FCM Validate] Starting token validation…');
        Log::info('[FCM Validate] Starting token validation');

        // ── 1. Collect all non-null tokens from users table ──────────────────
        $users = DB::table('users')
            ->whereNotNull('fcm_token')
            ->select('id', 'name', 'fcm_token', 'updated_at')
            ->get();

        $this->info("[FCM Validate] Found {$users->count()} users with tokens");

        $sent    = 0;
        $cleared = 0;
        $skipped = 0;

        // Deduplicate tokens (one user may have multiple rows — unlikely but safe)
        $seen = [];

        foreach ($users as $user) {
            $token = $user->fcm_token;
            if (isset($seen[$token])) { $skipped++; continue; }
            $seen[$token] = true;

            if ($this->option('dry-run')) {
                $this->line("  [dry] Would validate token for user #{$user->id}");
                continue;
            }

            // Send a silent data-only ping (no notification shown to user).
            // FcmService::sendToToken handles auto-clear on 403/404 errors.
            // Title/body are empty strings — FCM v1 with data-only: no visible notification.
            $ok = FcmService::sendSilentPing($token);

            if ($ok) {
                $sent++;
            } else {
                // Token was invalid — FcmService already cleared it.
                $cleared++;
            }
        }

        $this->info("[FCM Validate] Done — sent:{$sent} cleared:{$cleared} skipped:{$skipped}");
        Log::info('[FCM Validate] Done', compact('sent', 'cleared', 'skipped'));

        // ── 2. Report users with null tokens (inactive) ──────────────────────
        $nullCount = DB::table('users')->whereNull('fcm_token')->count();
        $this->info("[FCM Validate] Users with null token (will re-register on next app open): {$nullCount}");

        return self::SUCCESS;
    }
}
