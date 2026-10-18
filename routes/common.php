<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Common / Shared Authenticated Routes
|--------------------------------------------------------------------------
|
| Routes accessible across all authenticated guards (Customer, Agent, Admin)
| such as private file downloads and in-app notification center actions.
|
*/

Route::middleware('auth:customer,agent,admin')->group(function () {
    // Private attachment streaming guarded by AttachmentPolicy
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');

    // In-App Notification Center
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});
