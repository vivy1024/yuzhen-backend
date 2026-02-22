<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TrainingPlanController;
use App\Http\Controllers\Api\PlanTemplateController;

/**
 * Training Plan Routes - 训练计划管理
 *
 * 支持AI导入 + 用户手动创建/编辑/复制
 *
 * @version 2.0.0
 * @date 2026-02-22
 */

Route::middleware('jwt.auth')->prefix('training')->group(function () {
    Route::get('/plans', [TrainingPlanController::class, 'index']);
    Route::post('/plans', [TrainingPlanController::class, 'store']);          // 手动创建
    Route::post('/plans/import', [TrainingPlanController::class, 'import']); // AI导入
    Route::get('/plans/{id}', [TrainingPlanController::class, 'show']);
    Route::put('/plans/{id}', [TrainingPlanController::class, 'update']);
    Route::delete('/plans/{id}', [TrainingPlanController::class, 'destroy']);
    Route::post('/plans/{id}/copy', [TrainingPlanController::class, 'copy']);

    // 模板库
    Route::get('/templates', [PlanTemplateController::class, 'index']);
    Route::post('/templates/{id}/use', [PlanTemplateController::class, 'useTemplate']);
});
