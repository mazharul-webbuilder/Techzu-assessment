<?php

use App\Http\Controllers\Customer\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landing / Home Route
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('landing-page');
})->name('home');

/*
|--------------------------------------------------------------------------
| Customer Portal Routes (Guard: customer)
|--------------------------------------------------------------------------
*/
Route::prefix('customer')->name('customer.')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    });

    Route::middleware('auth:customer')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', function () {
            return view('customer.dashboard');
        })->name('dashboard');
    });
});
