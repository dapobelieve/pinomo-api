<?php

use App\Http\Controllers\Api\V1\AccountController;
use Illuminate\Support\Facades\Route;

Route::prefix('accounts')->name('accounts.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::post('/', [AccountController::class, 'store'])->name('store');
    Route::get('/{account}', [AccountController::class, 'show'])->name('show');
    Route::put('/{account}', [AccountController::class, 'update'])->name('update');
    Route::delete('/{account}', [AccountController::class, 'destroy'])->name('destroy');
    
    // Account state management
    Route::post('/{account}/activate', [AccountController::class, 'activate'])->name('activate');
    Route::post('/{account}/suspend', [AccountController::class, 'suspend'])->name('suspend');
    Route::post('/{account}/close', [AccountController::class, 'close'])->name('close');
    
    // Fund management
    Route::post('/{account}/lock', [AccountController::class, 'lockFunds'])->name('lock');
    Route::post('/{account}/unlock', [AccountController::class, 'unlockFunds'])->name('unlock');
    Route::post('/{account}/overdraft', [AccountController::class, 'overdraft'])->name('overdraft');
});

Route::patch('accounts/{account}/transaction-limit', [AccountController::class, 'updateTransactionLimit'])
    ->middleware(['auth:sanctum'])
    ->name('accounts.updateTransactionLimit');