<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ModulesUpdated implements ShouldBroadcastNow
{
    public function __construct(public readonly array $modules) {}

    public function broadcastOn(): Channel
    {
        return new Channel('modules');
    }

    public function broadcastAs(): string
    {
        return 'modules.updated';
    }
}
