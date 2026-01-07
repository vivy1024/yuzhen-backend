<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Help\Controllers\HelpController;

/**
 * Help Module Routes
 * 
 * 帮助中心模块路由
 * 
 * 前缀: /api/help
 */

Route::prefix('help')->group(function () {
    
    // 获取FAQ列表（公开接口）
    Route::get('/faqs', [HelpController::class, 'index']);
    
    // 获取FAQ详情（公开接口）
    Route::get('/faqs/{id}', [HelpController::class, 'show']);
    
    // 获取分类列表（公开接口）
    Route::get('/categories', [HelpController::class, 'categories']);
    
    // 提交FAQ反馈（公开接口，但可以考虑限流）
    Route::post('/faqs/{id}/feedback', [HelpController::class, 'feedback']);
    
});
