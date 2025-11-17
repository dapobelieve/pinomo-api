<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Utils\ResponseUtils;
use App\Jobs\SendEmailJob;
use App\Mail\EmailVerificationMail;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;

class EmailVerificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    public function verify(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return ResponseUtils::error('Email already verified', 400);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return ResponseUtils::success(null, 'Email has been verified');
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return ResponseUtils::error('Email already verified', 400);
        }

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $request->user()->getKey(), 'hash' => sha1($request->user()->getEmailForVerification())]
        );

        SendEmailJob::dispatch(
            $request->user()->email,
            new EmailVerificationMail($verificationUrl)
        );

        return ResponseUtils::success(null, 'Verification link will be sent');
    }
}