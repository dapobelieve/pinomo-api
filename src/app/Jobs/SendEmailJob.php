<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $mailable;

    public function __construct(string $email, $mailable)
    {
        $this->email = $email;
        $this->mailable = $mailable;
    }

    public function handle(): void
    {
        Mail::to($this->email)->send($this->mailable);
    }
}