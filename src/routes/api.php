<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/transactions', [App\Http\Controllers\Api\V1\TransactionController::class, 'userTransactions'])->name('transactions.user');
    Route::post('/transactions', [App\Http\Controllers\Api\V1\TransactionController::class, 'transfer'])->name('transactions.transfer');
});

Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/v1/auth.php';
    require __DIR__ . '/api/v1/client.php';
    require __DIR__ . '/api/v1/account.php';
    require __DIR__ . '/api/v1/transaction.php';
    require __DIR__ . '/api/v1/wallet.php';
    require __DIR__ . '/api/v1/kyc.php';
    require __DIR__ . '/api/v1/product.php';
    require __DIR__ . '/api/v1/gl_account.php';
    require __DIR__ . '/api/v1/charge.php';
    require __DIR__ . '/api/v1/reports.php';
    require __DIR__ . '/api/v1/journal_entry.php';
});
