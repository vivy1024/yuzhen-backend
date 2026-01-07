<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Feedback\Controllers\FeedbackController;

/**
 * Feedback Module Routes
 * 
 * 用户反馈模块路由
 * 
 * 前缀: /api/feedback
 * 中间件: jwt.auth
 */

Route::prefix('feedback')->middleware('jwt.auth')->group(function () {
    
    // 获取用户的反馈列表
    Route::get('/', [FeedbackController::class, 'index']);
    
    // 提交反馈
    Route::post('/', [FeedbackController::class, 'store']);
    
    // 获取反馈详情
    Route::get('/{id}', [FeedbackController::class, 'show']);
    
    // 上传截图
    Route::post('/upload', [FeedbackController::class, 'uploadImage']);
    
});
