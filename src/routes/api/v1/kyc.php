<?php

use App\Http\Controllers\Api\V1\KycDocumentController;
use App\Http\Controllers\Api\V1\KycStatusController;
use App\Http\Controllers\Api\V1\KycStorageConfigController;
use Illuminate\Support\Facades\Route;

Route::prefix('clients/{client}')->group(function () {
    Route::get('kyc-documents', [KycDocumentController::class, 'index']);
    Route::post('kyc-documents', [KycDocumentController::class, 'store']);
    Route::get('kyc-documents/{document}', [KycDocumentController::class, 'show']);
    Route::get('kyc-documents/{document}/download', [KycDocumentController::class, 'download']);
    Route::post('kyc-documents/{document}/review', [KycDocumentController::class, 'review']);

    Route::put('kyc-status', [KycStatusController::class, 'update']);
    Route::get('kyc-status/history', [KycStatusController::class, 'history']);

    // KYC Storage Configuration Routes
    Route::apiResource('kyc-storage-configs', KycStorageConfigController::class);
});