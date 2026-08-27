<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverSosAlert implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly array $payload) {}

    public function broadcastOn(): Channel
    {
        return new Channel('admin.sos');
    }

    public function broadcastAs(): string
    {
        return 'sos.alert';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
