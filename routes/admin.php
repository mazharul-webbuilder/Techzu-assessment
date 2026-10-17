<?php

use App\Http\Controllers\Admin\Auth\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administrator Portal Routes (Guard: admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Administrator Global Tickets & Assignment
        Route::get('/tickets', [\App\Http\Controllers\Admin\TicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/{ticket}', [\App\Http\Controllers\Admin\TicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/assign', [\App\Http\Controllers\Admin\TicketController::class, 'assign'])->name('tickets.assign');
        Route::post('/tickets/{ticket}/auto-assign', [\App\Http\Controllers\Admin\TicketController::class, 'autoAssign'])->name('tickets.auto-assign');
        Route::post('/tickets/{ticket}/reply', [\App\Http\Controllers\Admin\TicketController::class, 'reply'])->name('tickets.reply');
        Route::post('/tickets/{ticket}/status', [\App\Http\Controllers\Admin\TicketController::class, 'changeStatus'])->name('tickets.status');
        Route::post('/tickets/{ticket}/priority', [\App\Http\Controllers\Admin\TicketController::class, 'changePriority'])->name('tickets.priority');
    });
});
