<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $orderId,
        public float $latitude,
        public float $longitude,
        public string $status
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('order.' . $this->orderId);
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'  => $this->orderId,
            'latitude'  => $this->latitude,
            'longitude' => $this->longitude,
            'status'    => $this->status,
            'timestamp' => now()->toISOString(),
        ];
    }
}
