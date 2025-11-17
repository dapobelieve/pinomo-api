<?php

use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('reports')->group(function () {
    Route::get('transactions', [ReportController::class, 'transactionReport']);
    Route::get('accounts/{account}/statement', [ReportController::class, 'accountStatement']);
    Route::get('audit-trail', [ReportController::class, 'auditTrail']);
    Route::get('gl-summary', [ReportController::class, 'glAccountSummary']);
    Route::get('trial-balance', [ReportController::class, 'trialBalance']);
    Route::get('journal-entries', [ReportController::class, 'journalEntries']);
    Route::get('revenue', [ReportController::class, 'revenueReport']);
});