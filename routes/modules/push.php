<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PushController;

/**
 * Push Notification Routes - 推送通知管理
 */
Route::middleware('jwt.auth')->prefix('push')->group(function () {
    Route::post('/subscribe', [PushController::class, 'subscribe']);
    Route::post('/unsubscribe', [PushController::class, 'unsubscribe']);
    Route::put('/reminder-time', [PushController::class, 'updateReminderTime']);
});
