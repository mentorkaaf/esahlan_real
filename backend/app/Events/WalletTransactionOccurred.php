<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletTransactionOccurred implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $userId,
        public readonly string $txType,      // 'credit' | 'debit'
        public readonly float  $amount,
        public readonly float  $balance,     // balance AFTER transaction
        public readonly string $note,
        public readonly string $method,
        public readonly string $createdAt,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'wallet.transaction';
    }

    public function broadcastWith(): array
    {
        return [
            'type'       => $this->txType,
            'amount'     => $this->amount,
            'balance'    => $this->balance,
            'note'       => $this->note,
            'method'     => $this->method,
            'created_at' => $this->createdAt,
        ];
    }
}
