<?php

use App\Http\Controllers\Agent\Auth\AuthController;
use App\Http\Controllers\Agent\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agent Portal Routes (Guard: agent)
|--------------------------------------------------------------------------
*/
Route::prefix('agent')->name('agent.')->group(function () {
    Route::middleware('guest:agent')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    });

    Route::middleware('auth:agent')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });
});
