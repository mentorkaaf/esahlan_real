<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewOrderCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlBody;

    public function __construct(string $customerName, string $orderNumber, string $moduleLabel, string $total)
    {
        $rendered = EmailTemplate::render('new_order_customer', [
            'name'         => $customerName,
            'order_number' => $orderNumber,
            'module'       => $moduleLabel,
            'total'        => $total,
        ]);
        $this->subject = $rendered['subject'] ?? "Order #{$orderNumber} Received — Thank You!";
        $this->htmlBody = $rendered['body'] ?? '';
    }

    public function build()
    {
        return $this->view('emails.dynamic');
    }
}
