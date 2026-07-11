<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Central enforcement for user privacy settings.
 * All controllers must go through these methods — never read user_settings directly.
 */
class PrivacyService
{
    // Cache privacy settings for 5 minutes per user
    private static function settings(int $userId): array
    {
        return Cache::remember("privacy:{$userId}", 300, function () use ($userId) {
            $row = DB::table('user_settings')->where('user_id', $userId)->first();
            $stored = $row ? json_decode($row->privacy ?? 'null', true) : null;
            $defaults = [
                'private_account'    => false,
                'who_can_follow'     => 'everyone',
                'who_can_message'    => 'everyone',
                'who_can_comment'    => 'everyone',
                'who_can_mention'    => 'everyone',
                'who_can_tag'        => 'followers',
                'who_can_remix'      => 'everyone',
                'hide_online_status' => true,
                'hide_followers'     => false,
                'hide_following'     => false,
                'hide_likes'         => false,
            ];
            return array_merge($defaults, is_array($stored) ? $stored : []);
        });
    }

    public static function clearCache(int $userId): void
    {
        Cache::forget("privacy:{$userId}");
    }

    // ── Block check ──────────────────────────────────────────────────────
    public static function isBlocked(int $viewerId, int $targetId): bool
    {
        $key = "block:{$viewerId}:{$targetId}";
        return Cache::remember($key, 60, function () use ($viewerId, $targetId) {
            return DB::table('community_blocks')
                ->where(function ($q) use ($viewerId, $targetId) {
                    $q->where('blocker_id', $viewerId)->where('blocked_id', $targetId);
                })
                ->orWhere(function ($q) use ($viewerId, $targetId) {
                    $q->where('blocker_id', $targetId)->where('blocked_id', $viewerId);
                })
                ->exists();
        });
    }

    // ── Follower check ────────────────────────────────────────────────────
    private static function isFollowing(int $viewerId, int $targetId): bool
    {
        return DB::table('community_follows')
            ->where('follower_id', $viewerId)
            ->where('following_id', $targetId)
            ->where('status', 'accepted')
            ->exists();
    }

    // ── Can $viewerId view $targetId's profile / posts? ───────────────────
    public static function canViewProfile(int $viewerId, int $targetId): bool
    {
        if ($viewerId === $targetId) return true;
        $s = self::settings($targetId);
        if (!($s['private_account'] ?? false)) return true;
        return self::isFollowing($viewerId, $targetId);
    }

    // ── Can $viewerId follow $targetId? ───────────────────────────────────
    public static function canFollow(int $viewerId, int $targetId): array
    {
        if ($viewerId === $targetId) return ['allowed' => false, 'reason' => 'Cannot follow yourself'];
        if (self::isBlocked($viewerId, $targetId)) return ['allowed' => false, 'reason' => 'blocked'];
        $s = self::settings($targetId);
        $rule = $s['who_can_follow'] ?? 'everyone';
        if ($rule === 'nobody') return ['allowed' => false, 'reason' => 'This user is not accepting new followers'];
        return ['allowed' => true];
    }

    // ── Can $viewerId send a message to $targetId? ────────────────────────
    public static function canMessage(int $viewerId, int $targetId): array
    {
        if ($viewerId === $targetId) return ['allowed' => true];
        if (self::isBlocked($viewerId, $targetId)) return ['allowed' => false, 'reason' => 'blocked'];
        $s = self::settings($targetId);
        $rule = $s['who_can_message'] ?? 'everyone';
        if ($rule === 'nobody') return ['allowed' => false, 'reason' => 'This user is not accepting messages'];
        if ($rule === 'followers' && !self::isFollowing($viewerId, $targetId)) {
            return ['allowed' => false, 'reason' => 'Only followers can message this user'];
        }
        return ['allowed' => true];
    }

    // ── Can $viewerId comment on a post owned by $ownerId? ────────────────
    public static function canComment(int $viewerId, int $ownerId): array
    {
        if ($viewerId === $ownerId) return ['allowed' => true];
        $s = self::settings($ownerId);
        $rule = $s['who_can_comment'] ?? 'everyone';
        if ($rule === 'nobody') return ['allowed' => false, 'reason' => 'Comments are disabled for this user'];
        if ($rule === 'followers' && !self::isFollowing($viewerId, $ownerId)) {
            return ['allowed' => false, 'reason' => 'Only followers can comment on this post'];
        }
        return ['allowed' => true];
    }

    // ── Can $viewerId mention $targetId? ─────────────────────────────────
    public static function canMention(int $viewerId, int $targetId): array
    {
        if ($viewerId === $targetId) return ['allowed' => true];
        $s = self::settings($targetId);
        $rule = $s['who_can_mention'] ?? 'everyone';
        if ($rule === 'nobody') return ['allowed' => false, 'reason' => 'This user cannot be mentioned'];
        if ($rule === 'followers' && !self::isFollowing($viewerId, $targetId)) {
            return ['allowed' => false, 'reason' => 'Only followers can mention this user'];
        }
        return ['allowed' => true];
    }

    // ── Is $targetId's online status visible to $viewerId? ────────────────
    public static function isOnlineVisible(int $viewerId, int $targetId): bool
    {
        if ($viewerId === $targetId) return true;
        $s = self::settings($targetId);
        return !($s['hide_online_status'] ?? true);
    }

    // ── Apply visibility filters to a transformUser() result ──────────────
    public static function applyVisibility(array $userData, int $viewerId): array
    {
        $targetId = $userData['id'] ?? 0;
        if ($viewerId === $targetId) return $userData;
        $s = self::settings($targetId);

        if ($s['hide_followers'] ?? false)  $userData['followers_count'] = null;
        if ($s['hide_following'] ?? false)  $userData['following_count'] = null;
        if ($s['hide_likes']     ?? false)  $userData['likes_count']     = null;

        // Hide online status
        if ($s['hide_online_status'] ?? true) {
            $userData['is_online']  = false;
            $userData['last_seen']  = null;
        }

        // Private account: hide post count and mark as private
        if ($s['private_account'] ?? false) {
            $isFollower = self::isFollowing($viewerId, $targetId);
            $userData['is_private'] = true;
            if (!$isFollower) {
                $userData['posts_count'] = null;
                $userData['posts']       = [];
            }
        }

        return $userData;
    }
}
