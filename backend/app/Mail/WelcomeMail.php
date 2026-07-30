<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $renderedBody;
    public string $renderedSubject;

    public function __construct(public string $userName)
    {
        $rendered = EmailTemplate::render('welcome', ['name' => $userName]);
        $this->renderedSubject = $rendered['subject'] ?? 'Welcome to eSahlan!';
        $this->renderedBody    = $rendered['body']    ?? "<p>Welcome, {$userName}!</p>";
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
