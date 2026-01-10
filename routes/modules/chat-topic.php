<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatTopicController;

/**
 * Chat Topic Routes - AI聊天话题管理
 * 
 * 提供话题的增删查改接口，用于组织用户的AI对话
 * 包含历史对话和会话管理功能
 * 
 * @version 2.0.0
 * @date 2026-01-11
 * @requirements 1.1-1.6 对话历史与上下文管理
 */

Route::middleware('jwt.auth')->prefix('chat')->group(function () {
    // 对话历史 - Requirements 1.2
    Route::get('/history', [ChatTopicController::class, 'history']);
    
    // 会话管理 - Requirements 1.5
    Route::get('/sessions', [ChatTopicController::class, 'sessions']);
    Route::get('/sessions/{sessionId}', [ChatTopicController::class, 'sessionDetail']);
    Route::delete('/sessions/{sessionId}', [ChatTopicController::class, 'deleteSession']);
    
    // 话题管理
    Route::get('/topics', [ChatTopicController::class, 'index']);
    Route::post('/topics', [ChatTopicController::class, 'store']);
    Route::get('/topics/{id}', [ChatTopicController::class, 'show']);
    Route::put('/topics/{id}', [ChatTopicController::class, 'update']);
    Route::delete('/topics/{id}', [ChatTopicController::class, 'destroy']);
    
    // 话题消息
    Route::get('/topics/{id}/messages', [ChatTopicController::class, 'messages']);
    Route::post('/topics/{id}/messages', [ChatTopicController::class, 'storeMessage']);
    Route::post('/topics/{id}/messages/sync', [ChatTopicController::class, 'syncMessages']);
});
