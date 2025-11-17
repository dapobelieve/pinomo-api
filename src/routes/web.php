<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/health', [HealthController::class, 'health'])->name('health.check');
