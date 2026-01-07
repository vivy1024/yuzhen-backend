<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TrainingPlanController;

/**
 * Training Plan Routes - 训练计划管理
 * 
 * 提供训练计划的导入、查询、更新和删除接口
 * 支持从AI聊天中导入训练计划
 * 
 * @version 1.0.0
 * @date 2025-01-02
 */

Route::middleware('jwt.auth')->prefix('training')->group(function () {
    // 训练计划管理
    Route::get('/plans', [TrainingPlanController::class, 'index']);
    Route::post('/plans/import', [TrainingPlanController::class, 'import']);
    Route::get('/plans/{id}', [TrainingPlanController::class, 'show']);
    Route::put('/plans/{id}', [TrainingPlanController::class, 'update']);
    Route::delete('/plans/{id}', [TrainingPlanController::class, 'destroy']);
});
