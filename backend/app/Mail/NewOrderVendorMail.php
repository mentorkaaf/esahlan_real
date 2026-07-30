<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewOrderVendorMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlBody;

    public function __construct(string $vendorName, string $orderNumber, string $moduleLabel, string $total, string $customerName)
    {
        $rendered = EmailTemplate::render('new_order_vendor', [
            'name'          => $vendorName,
            'order_number'  => $orderNumber,
            'module'        => $moduleLabel,
            'total'         => $total,
            'customer_name' => $customerName,
        ]);
        $this->subject = $rendered['subject'] ?? "New Order #{$orderNumber} Received";
        $this->htmlBody = $rendered['body'] ?? '';
    }

    public function build()
    {
        return $this->view('emails.dynamic');
    }
}
