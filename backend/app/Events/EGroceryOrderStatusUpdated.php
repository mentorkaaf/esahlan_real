<?php

namespace App\Events;

use App\Models\EGrocery\EGroceryOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EGroceryOrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public EGroceryOrder $order) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->order->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'egrocery.order.status';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
            'status'   => $this->order->status,
        ];
    }
}
