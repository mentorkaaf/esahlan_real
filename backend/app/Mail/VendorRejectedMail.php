<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlBody;

    public function __construct(string $vendorName, string $reason = '')
    {
        $rendered = EmailTemplate::render('vendor_rejected', [
            'name'   => $vendorName,
            'reason' => $reason ?: 'Your application did not meet our current requirements.',
        ]);
        $this->subject = $rendered['subject'] ?? 'Update on your eSahlan vendor application';
        $this->htmlBody = $rendered['body'] ?? '';
    }

    public function build()
    {
        return $this->view('emails.dynamic');
    }
}
