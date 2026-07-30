<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeVendorMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $renderedBody;
    public string $renderedSubject;

    public function __construct(public string $vendorName)
    {
        $rendered = EmailTemplate::render('welcome_vendor', ['name' => $vendorName]);
        $this->renderedSubject = $rendered['subject'] ?? 'Welcome to eSahlan Vendor Platform';
        $this->renderedBody    = $rendered['body']    ?? "<p>Welcome, {$vendorName}!</p>";
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->renderedSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.dynamic', with: ['htmlBody' => $this->renderedBody]);
    }
}
