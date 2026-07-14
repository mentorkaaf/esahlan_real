<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StrikeService
{
    const MAX_STRIKES = 3;

    public static function addStrike(int $userId, string $reason, ?int $postId = null): void
    {
        $user = User::find($userId);
        if (!$user || $user->status === 'banned') return;

        // Record the strike
        DB::table('user_strikes')->insert([
            'user_id'    => $userId,
            'reason'     => $reason,
            'post_id'    => $postId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $totalStrikes = DB::table('user_strikes')->where('user_id', $userId)->count();

        Log::info("[Strike] User #$userId now has $totalStrikes strike(s). Reason: $reason");

        if ($totalStrikes >= self::MAX_STRIKES && $user->status !== 'restricted') {
            // Auto-restrict
            $user->update(['status' => 'restricted']);
            self::sendRestrictedNotification($user, $totalStrikes);
        } else {
            self::sendStrikeNotification($user, $totalStrikes, $reason);
        }
    }

    public static function getStrikes(int $userId): int
    {
        return DB::table('user_strikes')->where('user_id', $userId)->count();
    }

    private static function sendStrikeNotification(User $user, int $count, string $reason): void
    {
        $remaining = self::MAX_STRIKES - $count;

        $title = "⚠️ Digniin - Strike $count/" . self::MAX_STRIKES;
        $body  = "Adigoo xadgubaaya sharciyada eSahlan ayaad ku dhacday Strike $count. "
               . ($remaining > 0
                   ? "Hadaad $remaining strike kale ku dhacdo, akoonkaagu wuu xidmi doonaa."
                   : "Tani waa digniin ugu dambeysa.");

        // In-app notification
        DB::table('community_notifications')->insert([
            'user_id'           => $user->id,
            'actor_id'          => $user->id,
            'type'              => 'strike_warning',
            'notifiable_type'   => 'App\\Models\\User',
            'notifiable_id'     => $user->id,
            'data'              => json_encode([
                'title'         => $title,
                'body'          => $body,
                'strike_count'  => $count,
                'max_strikes'   => self::MAX_STRIKES,
                'reason'        => $reason,
            ]),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // FCM push directly via token (bypass auth()->id() check in send_community_push)
        self::pushToUser($user, $title, $body, [
            'type'         => 'strike_warning',
            'strike_count' => (string) $count,
            'deep_link'    => '/community/notifications',
        ]);
    }

    private static function sendRestrictedNotification(User $user, int $count): void
    {
        $title = "🚫 Akoonkaaga waa la xidhi doonaa";
        $body  = "Sababtoo ah adigoo $count jeer xadgubaaya sharciyada eSahlan, akoonkaaga hadda waa la xidhi doonaa. "
               . "Haddaad u maleynayso in ay khalad tahay, xiriir nala la xidhiidh.";

        DB::table('community_notifications')->insert([
            'user_id'           => $user->id,
            'actor_id'          => $user->id,
            'type'              => 'account_restricted',
            'notifiable_type'   => 'App\\Models\\User',
            'notifiable_id'     => $user->id,
            'data'              => json_encode([
                'title'         => $title,
                'body'          => $body,
                'strike_count'  => $count,
                'restricted'    => true,
            ]),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        self::pushToUser($user, $title, $body, [
            'type'       => 'account_restricted',
            'restricted' => 'true',
            'deep_link'  => '/community/notifications',
        ]);
    }

    private static function pushToUser(\App\Models\User $user, string $title, string $body, array $data = []): void
    {
        if (!$user->fcm_token) {
            Log::info("[Strike] No FCM token for user #$user->id — skipping push");
            return;
        }
        try {
            \App\Services\FcmService::sendToToken($user->fcm_token, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::warning("[Strike] FCM push failed for user #$user->id: " . $e->getMessage());
        }
    }
}
