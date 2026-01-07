<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Training\Controllers\TrainingPlanController;

/**
 * Training Module Routes
 *
 * 训练模块路由
 *
 * 前缀: /api/training-plans (与前端一致)
 *
 * @updated 2025-11-01
 * @version 1.1.0
 */

// 训练计划路由组
Route::prefix('training-plans')->middleware(['jwt.auth'])->group(function () {

    // 获取用户的训练计划列表
    Route::get('/', [TrainingPlanController::class, 'index']);

    // 获取训练计划详情
    Route::get('/{id}', [TrainingPlanController::class, 'show']);

    // 获取训练计划进度统计
    Route::get('/{id}/progress-stats', [TrainingPlanController::class, 'progressStats']);

    // 获取训练计划关联的训练日志
    Route::get('/{id}/training-logs', [TrainingPlanController::class, 'trainingLogs']);

    // 创建训练计划
    Route::post('/', [TrainingPlanController::class, 'store']);

    // 从AI对话创建训练计划
    Route::post('/ai-import', [TrainingPlanController::class, 'aiImport']);

    // 更新训练计划
    Route::put('/{id}', [TrainingPlanController::class, 'update']);

    // 删除训练计划（软删除）
    Route::delete('/{id}', [TrainingPlanController::class, 'destroy']);

    // 激活训练计划（设为当前使用）
    Route::put('/{id}/activate', [TrainingPlanController::class, 'activate']);

    // 完成训练计划（标记为已完成）
    Route::post('/{id}/complete', [TrainingPlanController::class, 'complete']);

});

