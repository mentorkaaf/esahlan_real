<?php

namespace App\Events\EWholesale;

use Illuminate\Broadcasting\{Channel, PrivateChannel};
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EWBuyerEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $buyerUserId,
        public readonly string $eventType,   // quote_received, order.status_updated, rfq_quote_received
        public readonly array  $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("ewholesale.buyer.{$this->buyerUserId}")];
    }

    public function broadcastAs(): string
    {
        return $this->eventType;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
