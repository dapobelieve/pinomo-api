<?php

use App\Http\Controllers\Api\V1\GLAccountController;
use Illuminate\Support\Facades\Route;

Route::prefix('gl-accounts')->group(function () {
    // Basic CRUD routes
    Route::get('/', [GLAccountController::class, 'index']);
    Route::post('/', [GLAccountController::class, 'store']);
    Route::get('/{glAccount}', [GLAccountController::class, 'show']);
    Route::put('/{glAccount}', [GLAccountController::class, 'update']);
    Route::delete('/{glAccount}', [GLAccountController::class, 'destroy']);
    
    // Additional route for account hierarchy
    Route::get('/tree', [GLAccountController::class, 'tree']);
});