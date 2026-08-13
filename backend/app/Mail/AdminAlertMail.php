<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    // Alert keys that contain financial keywords — use neutral subjects
    private const FINANCIAL_KEYS = [
        'wallet_topup', 'wallet_topup_request', 'withdrawal_request',
        'large_transaction', 'new_global_order', 'global_order_refunded',
    ];

    public function __construct(
        public string $alertKey,
        public string $alertSubject,
        public array  $alertData,
        public string $alertLabel,
    ) {
        // Financial alert subjects trigger Gmail spam filters.
        // Use a neutral format: "eSahlan: {Label} Notification" which
        // avoids keywords like "Wallet", "Top-up", "Withdrawal", "$amount".
        if (in_array($alertKey, self::FINANCIAL_KEYS)) {
            $this->subject = 'eSahlan: ' . $alertLabel . ' Notification';
        } else {
            $this->subject = '[eSahlan Alert] ' . $alertSubject;
        }
    }

    public function build(): static
    {
        return $this->view('emails.admin_alert')
            // Priority headers — inbox delivery hint for Gmail/Outlook
            ->withSymfonyMessage(function (\Symfony\Component\Mime\Email $message) {
                $message->getHeaders()
                    ->addTextHeader('X-Priority', '1')
                    ->addTextHeader('X-MSMail-Priority', 'High')
                    ->addTextHeader('Importance', 'high')
                    ->addTextHeader('X-Mailer', 'eSahlan-AdminAlert/1.0')
                    // Unique reference prevents Gmail grouping these as spam threads
                    ->addTextHeader('X-Entity-Ref-ID', uniqid('esahlan-', true));

                // Reply-To = to address — Gmail rarely spam-filters when
                // reply-to matches recipient (self-to-self pattern)
                foreach ($message->getTo() as $addr) {
                    $message->replyTo($addr->getAddress(), 'eSahlan Admin');
                    break; // first recipient only
                }
            });
    }
}
