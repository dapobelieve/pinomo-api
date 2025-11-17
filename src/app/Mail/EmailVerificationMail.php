<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $verificationUrl;

    public function __construct(string $verificationUrl)
    {
        $this->verificationUrl = $verificationUrl;
    }

    public function build()
    {
        return $this->markdown('emails.verify-email')
            ->subject('Verify Email Address')
            ->with([
                'verificationUrl' => $this->verificationUrl,
            ]);
    }
}