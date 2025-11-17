<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register'])->name('auth.register');
Route::post('login', [AuthController::class, 'login'])->name('auth.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('me', [AuthController::class, 'me'])->name('auth.me');
});

// Password Reset Routes
Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('auth.forgotPassword');
Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('auth.resetPassword');

// Email Verification Routes
Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->name('verification.verify');
Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->name('verification.send');

// Two-factor authentication routes
Route::middleware(['auth:sanctum'])->prefix('two-factor')->name('auth.twoFactor.')->group(function () {
    Route::post('/enable', [TwoFactorAuthenticationController::class, 'enable'])->name('enable');
    Route::post('/confirm', [TwoFactorAuthenticationController::class, 'confirm'])->name('confirm');
    Route::post('/disable', [TwoFactorAuthenticationController::class, 'disable'])->name('disable');
    Route::post('/verify', [TwoFactorAuthenticationController::class, 'verify'])->name('verify');
});