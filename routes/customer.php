<?php

use App\Http\Controllers\Customer\Auth\AuthController;
use App\Http\Controllers\Customer\TicketController;
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

        // Customer Tickets & Dashboard
        Route::get('/dashboard', [TicketController::class, 'index'])->name('dashboard');
        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    });
});
