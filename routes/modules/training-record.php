<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TrainingRecordController;

/**
 * 训练记录路由
 * 
 * 用于记录用户的训练数据和力量进步
 * 
 * @version 1.0.0
 * @date 2025-12-19
 */

Route::prefix('training')->middleware('jwt.auth')->group(function () {

    // 记录训练数据
    Route::post('/record', [TrainingRecordController::class, 'recordTraining']);

    // 批量记录训练数据
    Route::post('/record-batch', [TrainingRecordController::class, 'recordTrainingBatch']);

    // 获取力量进步曲线（所有动作）
    Route::get('/progress', [TrainingRecordController::class, 'getStrengthProgress']);

    // 获取力量进步曲线（特定动作）
    Route::get('/progress/{exercise_name}', [TrainingRecordController::class, 'getStrengthProgress']);

    // 删除训练记录
    Route::delete('/record/{exercise_name}/{index}', [TrainingRecordController::class, 'deleteTrainingRecord']);

});
