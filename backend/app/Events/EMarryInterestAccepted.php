<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EMarryInterestAccepted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $senderId,
        public readonly string $accepterName,
        public readonly int    $accepterId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->senderId}")];
    }

    public function broadcastAs(): string
    {
        return 'emarry.interest.accepted';
    }

    public function broadcastWith(): array
    {
        return [
            'accepter_id'   => $this->accepterId,
            'accepter_name' => $this->accepterName,
        ];
    }
}
