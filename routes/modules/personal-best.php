<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Training\Controllers\PersonalBestController;

/**
 * Personal Best Module Routes - 个人最佳记录路由
 *
 * 实现个人最佳记录的CRUD和自动更新API
 *
 * 前缀: /api/personal-bests
 *
 * @version 1.0.0
 * @date 2025-12-26
 * 
 * Requirements: 6.4
 */

Route::prefix('personal-bests')->middleware(['jwt.auth'])->group(function () {

    // 获取用户的所有个人最佳记录
    // GET /api/personal-bests
    Route::get('/', [PersonalBestController::class, 'index']);

    // 获取力量排行榜
    // GET /api/personal-bests/leaderboard
    Route::get('/leaderboard', [PersonalBestController::class, 'leaderboard']);

    // 更新个人最佳记录（自动判断是否打破记录）
    // POST /api/personal-bests/update
    Route::post('/update', [PersonalBestController::class, 'updatePersonalBest']);

    // 批量更新个人最佳记录
    // POST /api/personal-bests/batch-update
    Route::post('/batch-update', [PersonalBestController::class, 'batchUpdate']);

    // 获取特定动作的个人最佳记录
    // GET /api/personal-bests/{exerciseId}
    Route::get('/{exerciseId}', [PersonalBestController::class, 'show']);

    // 删除个人最佳记录
    // DELETE /api/personal-bests/{exerciseId}
    Route::delete('/{exerciseId}', [PersonalBestController::class, 'destroy']);

});
