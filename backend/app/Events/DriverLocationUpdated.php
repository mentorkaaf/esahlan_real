<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $driverId,
        public int $userId,
        public string $driverName,
        public float $latitude,
        public float $longitude,
        public string $status,
        public ?int $orderId = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('admin.drivers');
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id'   => $this->driverId,
            'driver_name' => $this->driverName,
            'latitude'    => $this->latitude,
            'longitude'   => $this->longitude,
            'status'      => $this->status,
            'order_id'    => $this->orderId,
            'timestamp'   => now()->toISOString(),
        ];
    }
}
