<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Food\Controllers\FoodController;

/**
 * Food Module Routes
 * 
 * 食物库API路由
 * 
 * @version 1.0.0
 * @date 2026-01-04
 */

Route::prefix('foods')->group(function () {
    // 获取分类列表
    Route::get('/categories', [FoodController::class, 'categories']);
    
    // 获取筛选选项
    Route::get('/filter-options', [FoodController::class, 'filterOptions']);
    
    // 清除缓存（用于数据更新后刷新）
    Route::get('/clear-cache', [FoodController::class, 'clearCache']);
    
    // 搜索食物
    Route::get('/search', [FoodController::class, 'search']);
    
    // 获取食物列表
    Route::get('/', [FoodController::class, 'index']);
    
    // 获取食物详情
    Route::get('/{id}', [FoodController::class, 'show'])->where('id', '[0-9]+');
});
