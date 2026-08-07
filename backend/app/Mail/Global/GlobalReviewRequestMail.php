<?php
namespace App\Mail\Global;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GlobalReviewRequestMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public readonly array $data) {}
    public function envelope(): Envelope {
        $subject = $this->data['is_reminder']
            ? 'Reminder: Share your experience with ' . $this->data['product_name']
            : 'How was your order? Leave a review ⭐';
        return new Envelope(subject: $subject);
    }
    public function content(): Content {
        return new Content(view: 'emails.global.review_request', with: ['data' => $this->data]);
    }
}
