<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $orderId,
        public readonly int    $messageId,
        public readonly string $senderType,  // 'customer' | 'driver'
        public readonly string $senderName,
        public readonly string $message,
        public readonly string $createdAt,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('order-chat.' . $this->orderId)];
    }

    public function broadcastAs(): string
    {
        return 'new_message';
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->messageId,
            'order_id'    => $this->orderId,
            'sender_type' => $this->senderType,
            'sender_name' => $this->senderName,
            'message'     => $this->message,
            'created_at'  => $this->createdAt,
        ];
    }
}
