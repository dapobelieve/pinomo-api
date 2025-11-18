<?php

use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('wallets')->name('wallets.')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [TransactionController::class, 'wallets'])->name('index');
    Route::get('/balance', [TransactionController::class, 'balance'])->name('balance');
    Route::get('/transactions', [TransactionController::class, 'transactions'])->name('transactions');
    Route::post('/deposit', [TransactionController::class, 'walletDeposit'])->name('deposit');
    Route::post('/transfer', [TransactionController::class, 'transfer'])->name('transfer');
});
