<?php

use App\Http\Controllers\Agent\Auth\AuthController;
use App\Http\Controllers\Agent\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Agent\TicketController;
use \App\Http\Controllers\Agent\AvailabilityController;

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

        // Agent Ticket Queues & Details
        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/claim', [TicketController::class, 'claim'])->name('tickets.claim');
        Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
        Route::post('/tickets/{ticket}/status', [TicketController::class, 'changeStatus'])->name('tickets.status');
        Route::post('/tickets/{ticket}/priority', [TicketController::class, 'changePriority'])->name('tickets.priority');

        // Agent Availability Toggle
        Route::post('/availability', [AvailabilityController::class, 'toggle'])->name('availability.toggle');
    });
});
