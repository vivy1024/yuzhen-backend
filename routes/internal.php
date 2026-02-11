<?php

use Illuminate\Support\Facades\Route;
use App\Modules\User\Controllers\InternalUserController;
use App\Modules\Membership\Controllers\InternalMembershipController;
use App\Modules\Chat\Controllers\InternalChatController;
use App\Modules\Training\Controllers\InternalTrainingController;
use App\Http\Controllers\Internal\InternalCreditController;
use App\Http\Controllers\Internal\UsageReportController;

/**
 * Internal API Routes
 * 
 * 用于MCP/CrewAI等内部服务访问
 * 
 * 认证: X-Internal-Token header
 * 前缀: /api/internal
 */

Route::prefix('internal')->middleware(['internal.api'])->group(function () {
    
    // 用户档案API（为MCP提供）
    Route::get('/user-profile/{userId}', [InternalUserController::class, 'getUserProfile']);
    Route::put('/user-profile/{userId}/volume-multiplier', [InternalUserController::class, 'updateVolumeMultiplier']);
    
    // 活跃用户API（为DAML-RAG缓存预热提供）
    Route::get('/users/active', [InternalUserController::class, 'getActiveUsers']);
    Route::get('/users/recent', [InternalUserController::class, 'getRecentUsers']);
    
    // 会员权限API（为AI对话系统提供）
    Route::post('/membership/check-permission', [InternalMembershipController::class, 'checkPermission']);
    Route::get('/membership/user/{userId}', [InternalMembershipController::class, 'getUserMembership']);
    Route::post('/membership/increment-usage', [InternalMembershipController::class, 'incrementUsage']);
    
    // 对话记录API（为MCO服务提供）
    Route::post('/chat/save-session', [InternalChatController::class, 'saveChatSession']);
    Route::post('/chat/update-feedback', [InternalChatController::class, 'updateFeedback']);
    Route::get('/chat/user/{userId}/history', [InternalChatController::class, 'getUserChatHistory']);
    Route::get('/chat/high-quality', [InternalChatController::class, 'getHighQualitySessions']);
    
    // 话题和消息API（为DAML-RAG多轮对话提供）
    Route::post('/chat/save-topic', [InternalChatController::class, 'saveTopic']);
    Route::post('/chat/save-message', [InternalChatController::class, 'saveMessage']);
    Route::delete('/chat/clear-topic/{topicId}', [InternalChatController::class, 'clearTopic']);
    
    // 三轨评分API（为DAML-RAG工作流步骤12提供）
    Route::post('/chat/update-personalization', [InternalChatController::class, 'updatePersonalization']);
    Route::get('/chat/session-count/{userId}', [InternalChatController::class, 'getSessionCount']);
    Route::get('/chat/fewshot-eligibility/{sessionId}', [InternalChatController::class, 'checkFewshotEligibility']);
    
    // Few-Shot降级搜索API（@requirements 4.6）
    Route::post('/chat/search-similar', [InternalChatController::class, 'searchSimilarConversations']);
    
    // 训练日志API（为DAML-RAG提供）
    Route::get('/training-logs/{userId}', [InternalTrainingController::class, 'getTrainingLogs']);
    Route::get('/training-logs/{userId}/stats', [InternalTrainingController::class, 'getTrainingStats']);
    
    // 个人最佳记录API（为DAML-RAG提供）
    Route::get('/personal-bests/{userId}', [InternalTrainingController::class, 'getPersonalBests']);
    Route::get('/personal-bests/{userId}/leaderboard', [InternalTrainingController::class, 'getLeaderboard']);
    Route::get('/personal-bests/{userId}/{exerciseId}', [InternalTrainingController::class, 'getPersonalBest']);
    Route::post('/personal-bests/{userId}/update', [InternalTrainingController::class, 'updatePersonalBest']);
    
    // 积分消耗记录API（为DAML-RAG提供）
    // @requirements 10.1 - DAML-RAG工作流完成后上报Token消耗
    Route::post('/credits/record', [InternalCreditController::class, 'recordConsumption']);
    
    // 用量上报API（为DAML-RAG权限系统重构提供）
    // @requirements 4.3 - DAML-RAG完成AI查询后上报用量
    Route::post('/usage/report', [UsageReportController::class, 'report']);
    
});

