<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $renderedBody;
    public string $renderedSubject;

    public function __construct(
        public string $code,
        public string $purpose,
        public string $userName = ''
    ) {
        $key      = 'otp_' . $purpose;
        $rendered = EmailTemplate::render($key, ['name' => $userName ?: 'User', 'code' => $code]);

        $this->renderedSubject = $rendered['subject'] ?? 'Your eSahlan Verification Code';
        $this->renderedBody    = $rendered['body']    ?? "<p>Your OTP: <strong>{$code}</strong></p>";
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
