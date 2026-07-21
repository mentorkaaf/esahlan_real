<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommunityStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly bool $enabled) {}

    public function broadcastOn(): array
    {
        return [new Channel('app.settings')];
    }

    public function broadcastAs(): string
    {
        return 'community.status_changed';
    }

    public function broadcastWith(): array
    {
        return ['enabled' => $this->enabled];
    }
}
