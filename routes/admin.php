<?php

use App\Http\Controllers\Admin\Auth\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\ImportController;


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
        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
        Route::post('/tickets/{ticket}/auto-assign', [TicketController::class, 'autoAssign'])->name('tickets.auto-assign');
        Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
        Route::post('/tickets/{ticket}/status', [TicketController::class, 'changeStatus'])->name('tickets.status');
        Route::post('/tickets/{ticket}/priority', [TicketController::class, 'changePriority'])->name('tickets.priority');

        // CSV Ticket Batch Imports
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::get('/imports/create', [ImportController::class, 'create'])->name('imports.create');
        Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
        Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
        Route::get('/imports/{import}/errors', [ImportController::class, 'downloadErrors'])->name('imports.errors');
    });
});
