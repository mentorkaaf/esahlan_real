<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class InboxMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $conversationUuid,
        public readonly mixed  $payload,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("inbox.{$this->conversationUuid}");
    }

    public function broadcastAs(): string { return 'message'; }

    public function broadcastWith(): array
    {
        return is_array($this->payload) ? $this->payload : ['data' => $this->payload];
    }
}
