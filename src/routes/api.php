<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/v1/auth.php';
    require __DIR__ . '/api/v1/client.php';
    require __DIR__ . '/api/v1/account.php';
    require __DIR__ . '/api/v1/transaction.php';
    require __DIR__ . '/api/v1/wallet.php';
    require __DIR__ . '/api/v1/kyc.php';
    require __DIR__ . '/api/v1/product.php';
    require __DIR__ . '/api/v1/gl_account.php';
    require __DIR__ . '/api/v1/charge.php';
    require __DIR__ . '/api/v1/reports.php';
    require __DIR__ . '/api/v1/journal_entry.php';
});
