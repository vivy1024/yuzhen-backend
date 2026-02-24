<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserSettingsController;
use App\Http\Controllers\Api\AvatarController;

/**
 * User Settings Routes - 用户设置与账号安全
 *
 * @version 1.0.0
 * @date 2026-02-24
 */

Route::middleware('jwt.auth')->group(function () {
    // 用户设置
    Route::get('/settings', [UserSettingsController::class, 'getSettings']);
    Route::put('/settings', [UserSettingsController::class, 'updateSettings']);

    // 账号安全
    Route::post('/user/change-password', [UserSettingsController::class, 'changePassword']);
    Route::delete('/user/account', [UserSettingsController::class, 'deleteAccount']);

    // 版本信息
    Route::get('/version', [UserSettingsController::class, 'getVersion']);

    // 头像上传
    Route::post('/users/avatar', [AvatarController::class, 'upload']);
});
