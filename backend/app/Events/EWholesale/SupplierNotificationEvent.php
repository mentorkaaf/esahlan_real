<?php

namespace App\Events\EWholesale;

use Illuminate\Broadcasting\{Channel, PrivateChannel};
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int    $vendorId,
        public readonly string $event,
        public readonly string $title,
        public readonly string $body,
        public readonly array  $data = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("vendor.{$this->vendorId}")];
    }

    public function broadcastAs(): string
    {
        return 'ew.notification';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => $this->event,
            'title' => $this->title,
            'body'  => $this->body,
            'data'  => $this->data,
        ];
    }
}
