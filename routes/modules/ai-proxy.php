<?php

/**
 * AI代理路由
 * 
 * 将前端的AI请求代理到DAML-RAG服务
 * 
 * @version 1.0.0
 * @created 2026-01-13
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiProxyController;

/*
|--------------------------------------------------------------------------
| AI代理路由
|--------------------------------------------------------------------------
|
| 这些路由将前端的AI请求代理到DAML-RAG服务
| 前缀: /ai
|
*/

Route::prefix('ai')->group(function () {
    // 流式聊天接口
    Route::post('/api/v1/chat/stream', [AiProxyController::class, 'streamChat']);
    
    // 非流式聊天接口
    Route::post('/api/v1/chat', [AiProxyController::class, 'chat']);
    
    // 健康检查
    Route::get('/api/health', [AiProxyController::class, 'health']);
});
