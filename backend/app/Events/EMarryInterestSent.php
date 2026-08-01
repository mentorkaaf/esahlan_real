<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EMarryInterestSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $receiverId,
        public readonly string $senderName,
        public readonly int    $senderId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->receiverId}")];
    }

    public function broadcastAs(): string
    {
        return 'emarry.interest';
    }

    public function broadcastWith(): array
    {
        return [
            'sender_id'   => $this->senderId,
            'sender_name' => $this->senderName,
        ];
    }
}
