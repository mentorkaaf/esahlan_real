<?php
namespace App\Services;

use App\Events\RealtimeEvent;

/**
 * Single entry point every module uses to broadcast realtime events.
 * Wraps the channel-naming convention documented in routes/channels.php so
 * feature code never constructs raw channel name strings or picks between
 * Channel/PrivateChannel/PresenceChannel itself.
 */
class RealtimeService
{
    /** Personal channel — notifications, order status, wallet updates, etc. */
    public static function toUser(int $userId, string $event, array $data): void
    {
        broadcast(new RealtimeEvent(
            [['channel' => "user.{$userId}", 'type' => 'private']],
            $event,
            $data,
        ));
    }

    /** Same event to many users at once — one job, one Redis publish per channel. */
    public static function toUsers(array $userIds, string $event, array $data): void
    {
        if (empty($userIds)) return;
        $channels = array_map(fn ($id) => ['channel' => "user.{$id}", 'type' => 'private'], array_unique($userIds));
        broadcast(new RealtimeEvent($channels, $event, $data));
    }

    /** Public, non-sensitive aggregate data (counts) — e.g. community.post.{id} */
    public static function toPublic(string $channel, string $event, array $data): void
    {
        broadcast(new RealtimeEvent(
            [['channel' => $channel, 'type' => 'public']],
            $event,
            $data,
        ));
    }

    /** Chat message / receipt events — members-only private channel */
    public static function toChat(int $chatId, string $event, array $data): void
    {
        broadcast(new RealtimeEvent(
            [['channel' => "chat.{$chatId}", 'type' => 'private']],
            $event,
            $data,
        ));
    }

    /** Chat presence events (typing, viewing) — members-only presence channel */
    public static function toChatPresence(int $chatId, string $event, array $data): void
    {
        broadcast(new RealtimeEvent(
            [['channel' => "chat-presence.{$chatId}", 'type' => 'presence']],
            $event,
            $data,
        ));
    }

    /** Owner-only sensitive detail on a resource — e.g. story viewer list */
    public static function toOwner(string $module, string $resource, int $resourceId, string $event, array $data): void
    {
        broadcast(new RealtimeEvent(
            [['channel' => "{$module}.{$resource}.{$resourceId}.owner", 'type' => 'private']],
            $event,
            $data,
        ));
    }

    /** Global online presence */
    public static function toOnlinePresence(string $event, array $data): void
    {
        broadcast(new RealtimeEvent(
            [['channel' => 'online', 'type' => 'presence']],
            $event,
            $data,
        ));
    }
}
