<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The single generic broadcast event for the entire eSahlan realtime layer.
 * Every module (Community, Chat, Delivery, Vendor, Wallet, Admin, future
 * modules) dispatches through this one class via RealtimeService instead of
 * defining a bespoke ShouldBroadcast event per feature — keeps the
 * broadcasting surface small and consistent as new modules adopt it.
 *
 * Queued (not ShouldBroadcastNow) so a request that triggers a broadcast to
 * many recipients (e.g. a new post fanning out to followers) doesn't block
 * on Redis publish calls. The supervisor-managed queue worker picks jobs up
 * within ~1s, which is imperceptible for this use case.
 */
class RealtimeEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $connection = 'database';
    public string $queue = 'broadcasts';

    /**
     * @param array<int, array{channel: string, type: 'public'|'private'|'presence'}> $channels
     * @param string $eventName Short, stable name the Flutter client listens for (e.g. "post.liked")
     * @param array $payload Keep this small — changed data only, never a full model dump
     */
    public function __construct(
        public array $channels,
        public string $eventName,
        public array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return array_map(fn (array $c) => match ($c['type']) {
            'private'  => new PrivateChannel($c['channel']),
            'presence' => new PresenceChannel($c['channel']),
            default    => new Channel($c['channel']),
        }, $this->channels);
    }

    public function broadcastAs(): string
    {
        return $this->eventName;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
