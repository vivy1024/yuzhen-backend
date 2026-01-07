<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Exercise\Controllers\ExerciseController;
use App\Modules\Exercise\Controllers\ExerciseFilterController;

/**
 * Exercise Module Routes
 * 
 * 动作库模块路由
 * 
 * 前缀: /api/exercises 和 /api/exercises-v2
 */

// exercises-v2 路由组（前端v2使用）
Route::prefix('exercises-v2')->group(function () {
    
    // 筛选选项
    Route::get('/filter-options', [ExerciseFilterController::class, 'options']);
    
    // 搜索
    Route::get('/search', [ExerciseController::class, 'search']);
    
    // 列表
    Route::get('/', [ExerciseController::class, 'index']);
    
    // 详情
    Route::get('/{id}', [ExerciseController::class, 'show'])
        ->where('id', '[0-9]+');
    
    // 以下需要认证
    Route::middleware(['jwt.auth'])->group(function () {
        
        // 创建动作（管理员）
        Route::post('/', [ExerciseController::class, 'store'])
            ->middleware('role:admin');
        
        // 更新动作（管理员）
        Route::put('/{id}', [ExerciseController::class, 'update'])
            ->middleware('role:admin');
        
        // 删除动作（管理员）
        Route::delete('/{id}', [ExerciseController::class, 'destroy'])
            ->middleware('role:admin');
        
    });
    
});

// exercises 路由组（兼容前端v1）
Route::prefix('exercises')->group(function () {
    
    // 筛选选项
    Route::get('/filter-options', [ExerciseFilterController::class, 'options']);
    
    // 搜索
    Route::get('/search', [ExerciseController::class, 'search']);
    
    // 列表
    Route::get('/', [ExerciseController::class, 'index']);
    
    // 详情
    Route::get('/{id}', [ExerciseController::class, 'show'])
        ->where('id', '[0-9]+');
    
    // 以下需要认证
    Route::middleware(['jwt.auth'])->group(function () {
        
        // 创建动作（管理员）
        Route::post('/', [ExerciseController::class, 'store'])
            ->middleware('role:admin');
        
        // 更新动作（管理员）
        Route::put('/{id}', [ExerciseController::class, 'update'])
            ->middleware('role:admin');
        
        // 删除动作（管理员）
        Route::delete('/{id}', [ExerciseController::class, 'destroy'])
            ->middleware('role:admin');
        
    });
    
});

