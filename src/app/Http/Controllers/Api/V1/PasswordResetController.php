<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Utils\ResponseUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class PasswordResetController extends Controller
{
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email'),
            function ($user, $token) {
                SendEmailJob::dispatch(
                    $user->email,
                    new PasswordResetMail($token, $user->email)
                );
            }
        );

        if ($status === Password::RESET_LINK_SENT) {
            return ResponseUtils::success(null, 'Password reset link will be sent to your email');
        }

        return ResponseUtils::error('Unable to send password reset link', 400);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return ResponseUtils::success(null, 'Password has been reset successfully');
        }

        return ResponseUtils::error('Unable to reset password', 400);
    }
}