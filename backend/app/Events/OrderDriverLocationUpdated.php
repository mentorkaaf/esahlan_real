<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts the driver's GPS position to the customer via the order chat channel.
 * Customer subscribes to private-order-chat.{orderId} and receives this every
 * ~10 seconds while delivery is in progress, allowing real-time map tracking.
 */
class OrderDriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int   $orderId,
        public readonly float $lat,
        public readonly float $lng,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('order-chat.' . $this->orderId)];
    }

    public function broadcastAs(): string
    {
        return 'driver_location';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'   => $this->orderId,
            'lat'        => $this->lat,
            'lng'        => $this->lng,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
