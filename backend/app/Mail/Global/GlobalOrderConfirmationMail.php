<?php

namespace App\Mail\Global;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GlobalOrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly array $order,
        public readonly array $items,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order Confirmed — ' . $this->order['order_number'] . ' ✓',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.global.order_confirmation',
        );
    }
}
