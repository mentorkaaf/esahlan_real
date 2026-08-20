<?php

namespace App\Events\EWholesale;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EWSupplierEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $supplierVendorId,
        public readonly string $eventType,   // new_order, inquiry_received, rfq_new
        public readonly array  $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("ewholesale.supplier.{$this->supplierVendorId}")];
    }

    public function broadcastAs(): string { return $this->eventType; }
    public function broadcastWith(): array { return $this->payload; }
}
