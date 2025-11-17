<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $token;
    protected $email;

    public function __construct(string $token, string $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function build()
    {
        return $this->markdown('emails.password-reset')
            ->subject('Reset Password Notification')
            ->with([
                'token' => $this->token,
                'email' => $this->email,
            ]);
    }
}