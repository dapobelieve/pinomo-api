<?php

use App\Http\Controllers\Api\V1\ChargeController;
use Illuminate\Support\Facades\Route;

// Basic charge management routes
Route::prefix('charges')->group(function () {
    Route::get('/', [ChargeController::class, 'index']);
    Route::post('/', [ChargeController::class, 'store']);
    Route::get('/{charge}', [ChargeController::class, 'show']);
    Route::put('/{charge}', [ChargeController::class, 'update']);
    Route::put('/{charge}/deactivate', [ChargeController::class, 'deactivate']);
});

// Account-specific charge routes
Route::post('accounts/{account}/apply-charge', [ChargeController::class, 'applyCharge']);