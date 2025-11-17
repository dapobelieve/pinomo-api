<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Utils\ResponseUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Str;

class TwoFactorAuthenticationController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function enable(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (!Hash::check($request->password, $request->user()->password)) {
            return ResponseUtils::error('The provided password is incorrect.', 422);
        }

        $secret = $this->google2fa->generateSecretKey();
        $recoveryCodes = collect(range(1, 8))->map(function () {
            return Str::random(10);
        })->all();

        $request->user()->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        return ResponseUtils::success([
            'secret' => $secret,
            'recovery_codes' => $recoveryCodes,
            'qr_code' => $this->google2fa->getQRCodeUrl(
                config('app.name'),
                $request->user()->email,
                $secret
            ),
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        $secret = decrypt($user->two_factor_secret);

        if ($this->google2fa->verifyKey($secret, $request->code)) {
            $user->two_factor_confirmed_at = now();
            $user->save();

            return ResponseUtils::success(['message' => 'Two-factor authentication has been enabled.']);
        }

        return ResponseUtils::error('The provided two-factor authentication code was invalid.', 422);
    }

    public function disable(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (!Hash::check($request->password, $request->user()->password)) {
            return ResponseUtils::error('The provided password is incorrect.', 422);
        }

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return ResponseUtils::success(['message' => 'Two-factor authentication has been disabled.']);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($request->code === 'recovery') {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes));
            
            if (in_array($request->recovery_code, $recoveryCodes)) {
                $recoveryCodes = array_diff($recoveryCodes, [$request->recovery_code]);
                $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
                $user->save();
                
                return ResponseUtils::success(['message' => 'The recovery code was accepted.']);
            }
            
            return ResponseUtils::error('The recovery code is invalid.', 422);
        }

        $secret = decrypt($user->two_factor_secret);

        if ($this->google2fa->verifyKey($secret, $request->code)) {
            return ResponseUtils::success(['message' => 'The provided two-factor authentication code was correct.']);
        }

        return ResponseUtils::error('The provided two-factor authentication code was invalid.', 422);
    }
}