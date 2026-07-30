<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlBody;

    public function __construct(string $customerName, string $orderNumber, string $status, string $moduleLabel = '')
    {
        $statusLabel = match($status) {
            'pending'           => 'Pending',
            'confirmed'         => 'Confirmed',
            'preparing'         => 'Preparing',
            'ready_for_pickup'  => 'Ready for Pickup',
            'out_for_delivery'  => 'Out for Delivery',
            'delivered'         => 'Delivered',
            'completed'         => 'Completed',
            'cancelled'         => 'Cancelled',
            'refunded'          => 'Refunded',
            'failed'            => 'Failed',
            default             => ucfirst($status),
        };

        $rendered = EmailTemplate::render('order_status', [
            'name'         => $customerName,
            'order_number' => $orderNumber,
            'status'       => $statusLabel,
            'module'       => $moduleLabel ?: 'eSahlan',
        ]);
        $this->subject = $rendered['subject'] ?? "Order #{$orderNumber} — {$statusLabel}";
        $this->htmlBody = $rendered['body'] ?? '';
    }

    public function build()
    {
        return $this->view('emails.dynamic');
    }
}
