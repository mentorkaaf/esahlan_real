<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Admin live-map event — broadcasts driver GPS to the admin dispatch dashboard.
 * Separate from OrderDriverLocationUpdated which targets the customer on the
 * per-order chat channel.
 */
class DriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int     $deliverymanId,
        public readonly int     $userId,
        public readonly string  $name,
        public readonly float   $lat,
        public readonly float   $lng,
        public readonly string  $status,
        public readonly ?int    $orderId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('admin.dispatch')];
    }

    public function broadcastAs(): string
    {
        return 'driver_location';
    }

    public function broadcastWith(): array
    {
        return [
            'deliveryman_id' => $this->deliverymanId,
            'user_id'        => $this->userId,
            'name'           => $this->name,
            'lat'            => $this->lat,
            'lng'            => $this->lng,
            'status'         => $this->status,
            'order_id'       => $this->orderId,
            'updated_at'     => now()->toIso8601String(),
        ];
    }
}
