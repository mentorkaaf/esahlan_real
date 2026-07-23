<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class InboxTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $conversationUuid,
        public readonly int    $userId,
        public readonly string $senderType,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("inbox.{$this->conversationUuid}");
    }

    public function broadcastAs(): string { return 'typing'; }

    public function broadcastWith(): array
    {
        return ['user_id' => $this->userId, 'sender_type' => $this->senderType];
    }
}
