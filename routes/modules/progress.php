<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Progress\Controllers\ProgressController;

/**
 * Progress Routes
 * 
 * 进度追踪相关路由
 * 
 * @version 1.0.0
 * @date 2026-01-06
 */

Route::prefix('progress')->middleware('jwt.auth')->group(function () {
    // 进度概览
    Route::get('/overview', [ProgressController::class, 'overview']);
    
    // 进度记录 CRUD
    Route::get('/records', [ProgressController::class, 'records']);
    Route::post('/records', [ProgressController::class, 'createRecord']);
    Route::get('/records/{id}', [ProgressController::class, 'getRecord']);
    Route::put('/records/{id}', [ProgressController::class, 'updateRecord']);
    Route::delete('/records/{id}', [ProgressController::class, 'deleteRecord']);
    
    // 目标管理 CRUD
    Route::get('/goals', [ProgressController::class, 'goals']);
    Route::post('/goals', [ProgressController::class, 'createGoal']);
    Route::put('/goals/{id}', [ProgressController::class, 'updateGoal']);
    Route::delete('/goals/{id}', [ProgressController::class, 'deleteGoal']);
    
    // 训练日历
    Route::get('/calendar', [ProgressController::class, 'calendar']);
    
    // 趋势数据
    Route::get('/trends/weight', [ProgressController::class, 'weightTrend']);
    Route::get('/trends/ffmi', [ProgressController::class, 'ffmiTrend']);
});
