<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Training\Controllers\TrainingLogController;

/**
 * Training Log Module Routes - 训练日志路由
 *
 * 实现闭环学习系统的训练日志API
 *
 * 前缀: /api/training-logs
 *
 * @version 1.0.0
 * @date 2025-12-26
 * 
 * Requirements: 6.1, 6.2, 6.3
 */

Route::prefix('training-logs')->middleware(['jwt.auth'])->group(function () {

    // 获取用户的训练日志列表
    // GET /api/training-logs
    Route::get('/', [TrainingLogController::class, 'index']);

    // 获取训练统计
    // GET /api/training-logs/stats
    Route::get('/stats', [TrainingLogController::class, 'stats']);

    // 获取训练日志详情
    // GET /api/training-logs/{id}
    Route::get('/{id}', [TrainingLogController::class, 'show'])->where('id', '[0-9]+');

    // 记录训练会话
    // POST /api/training-logs/session
    Route::post('/session', [TrainingLogController::class, 'recordSession']);

    // 记录单组训练
    // POST /api/training-logs/{id}/set
    Route::post('/{id}/set', [TrainingLogController::class, 'recordSet'])->where('id', '[0-9]+');

    // 更新训练日志
    // PUT /api/training-logs/{id}
    Route::put('/{id}', [TrainingLogController::class, 'update'])->where('id', '[0-9]+');

    // 删除训练日志
    // DELETE /api/training-logs/{id}
    Route::delete('/{id}', [TrainingLogController::class, 'destroy'])->where('id', '[0-9]+');

});
