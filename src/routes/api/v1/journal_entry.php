<?php

use App\Http\Controllers\Api\V1\JournalEntryController;
use Illuminate\Support\Facades\Route;

Route::prefix('journal-entries')->group(function () {
    Route::get('/', [JournalEntryController::class, 'index']);
    Route::post('/', [JournalEntryController::class, 'store']);
    Route::get('/{journal_entry}', [JournalEntryController::class, 'show']);
    Route::put('/{journal_entry}', [JournalEntryController::class, 'update']);
    Route::delete('/{journal_entry}', [JournalEntryController::class, 'destroy']);
    
    // Special actions
    Route::post('/{journal_entry}/post', [JournalEntryController::class, 'post']);
    Route::post('/{journal_entry}/void', [JournalEntryController::class, 'void']);
});