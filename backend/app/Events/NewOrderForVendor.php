<?php
namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewOrderForVendor implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('vendor.' . $this->order->vendor_id)];
    }

    public function broadcastAs(): string
    {
        return 'order.new';
    }

    public function broadcastWith(): array
    {
        $items = $this->order->items ?? collect();
        return [
            'order_id'      => $this->order->id,
            'order_number'  => $this->order->order_number,
            'total_amount'  => $this->order->total_amount,
            'items_count'   => $items->count(),
            'customer_name' => $this->order->user?->name ?? 'Customer',
            'module_slug'   => $this->order->module_slug,
            'payment_method'=> $this->order->payment_method,
            'note'          => $this->order->note,
            'placed_at'     => $this->order->placed_at,
            'timestamp'     => now()->toISOString(),
        ];
    }
}
