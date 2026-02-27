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

    // 提交FAQ反馈（限流：每IP每分钟5次，防止垃圾反馈轰炸）
    Route::middleware('throttle:5,1')->post('/faqs/{id}/feedback', [HelpController::class, 'feedback']);

});
