<?php
namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order, public string $newStatus) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('order.' . $this->order->id),
            new Channel('user.' . $this->order->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'   => $this->order->id,
            'order_number'=> $this->order->order_number,
            'status'     => $this->newStatus,
            'timestamp'  => now()->toISOString(),
        ];
    }
}
