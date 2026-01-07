<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatTopicController;

/**
 * Chat Topic Routes - AI聊天话题管理
 * 
 * 提供话题的增删查改接口，用于组织用户的AI对话
 * 
 * @version 1.0.0
 * @date 2025-01-02
 */

Route::middleware('jwt.auth')->prefix('chat')->group(function () {
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
