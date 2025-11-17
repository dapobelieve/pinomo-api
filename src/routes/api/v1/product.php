<?php

use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ChargeController;
use Illuminate\Support\Facades\Route;

// Product routes
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::post('/', [ProductController::class, 'store']);
    Route::get('/{product}', [ProductController::class, 'show']);
    Route::put('/{product}', [ProductController::class, 'update']);
    Route::delete('/{product}', [ProductController::class, 'destroy']);
    Route::post('/{product}/charges', [ProductController::class, 'attachCharges']);
});

// Charge routes
Route::prefix('charges')->group(function () {
    Route::get('/', [ChargeController::class, 'index']);
    Route::post('/', [ChargeController::class, 'store']);
    Route::get('/{charge}', [ChargeController::class, 'show']);
    Route::put('/{charge}', [ChargeController::class, 'update']);
    Route::delete('/{charge}', [ChargeController::class, 'destroy']);
});