<?php

use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('wallet')->name('wallet.')->middleware('auth:sanctum')->group(function () {
    Route::get('/balance', [TransactionController::class, 'balance'])->name('balance');
    Route::get('/transactions', [TransactionController::class, 'transactions'])->name('transactions');
    Route::post('/transfer', [TransactionController::class, 'transfer'])->name('transfer');
});
