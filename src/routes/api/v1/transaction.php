<?php

use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\WebhookDemoController;
use Illuminate\Support\Facades\Route;

Route::prefix('transactions')->name('transactions.')->group(function () {
    Route::get('/', [TransactionController::class, 'index'])->name('index');

    // Deposits
    Route::post('/deposits/{account_id}', [TransactionController::class, 'deposit'])->name('deposit');

    Route::get('/{transaction_id}', [TransactionController::class, 'show'])->name('show');
     Route::get('/{transaction_id}/status', [TransactionController::class, 'status'])->name('status');

    // Withdrawals
    Route::post('/withdrawals/{account_id}', [TransactionController::class, 'withdraw'])->name('withdraw');

    // Liens
    Route::post('/liens/{account_id}', [TransactionController::class, 'placeLien'])->name('liens.place');
    Route::put('/liens/{transaction}', [TransactionController::class, 'releaseLien'])->name('liens.release');
    Route::post('/liens/{transaction}/release-and-withdraw', [TransactionController::class, 'releaseAndWithdraw'])
        ->name('liens.releaseAndWithdraw');


    // Account Statement
    Route::get('/accounts/{accountId}/statement', [TransactionController::class, 'statement'])->name('statement');

    // Transaction History
    Route::get('/accounts/{account_id}/history', [TransactionController::class, 'history'])->name('history');
    Route::get('/accounts/all-history', [TransactionController::class, 'allHistory'])->name('all-history');

    // Reversals
    Route::post('/{id}/reversals', [TransactionController::class, 'reverse'])->name('reverse');
});

// Demo webhook endpoints
Route::prefix('webhooks/demo')->group(function () {
    Route::post('/receive', [WebhookDemoController::class, 'receive']);
    Route::get('/logs', [WebhookDemoController::class, 'logs']);
});
