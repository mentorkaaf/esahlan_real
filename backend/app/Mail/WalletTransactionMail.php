<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WalletTransactionMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlBody;

    public function __construct(string $userName, string $type, float $amount, float $balance, string $note = '')
    {
        $key = $type === 'credit' ? 'wallet_credit' : 'wallet_debit';
        $rendered = EmailTemplate::render($key, [
            'name'    => $userName,
            'amount'  => number_format($amount, 2),
            'balance' => number_format($balance, 2),
            'note'    => $note ?: ($type === 'credit' ? 'Wallet funded' : 'Wallet payment'),
        ]);
        $this->subject = $rendered['subject'] ?? ($type === 'credit' ? 'Wallet Credited' : 'Wallet Payment');
        $this->htmlBody = $rendered['body'] ?? '';
    }

    public function build()
    {
        return $this->view('emails.dynamic');
    }
}
