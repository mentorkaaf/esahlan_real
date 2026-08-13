<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $alertKey,
        public string $alertSubject,
        public array  $alertData,
        public string $alertLabel,
    ) {
        $this->subject = '[eSahlan Alert] ' . $alertSubject;
    }

    public function build(): static
    {
        return $this->view('emails.admin_alert');
    }
}
