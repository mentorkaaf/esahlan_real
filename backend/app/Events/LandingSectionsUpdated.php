<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LandingSectionsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $sections) {}

    public function broadcastOn(): array
    {
        return [new Channel('landing')];
    }

    public function broadcastAs(): string
    {
        return 'sections.updated';
    }

    public function broadcastWith(): array
    {
        return ['sections' => $this->sections];
    }
}
