<?php

/**
 * Quality Rating Module - 三轨评分系统API
 * 
 * 实现三轨评分体系：
 * 1. 用户体验评分 (5维度)
 * 2. 个性化感知评分 (4维度，自动计算)
 * 3. 专家专业评分 (6维度)
 * 
 * API端点：
 * - POST /api/v2/quality/rating - 提交评分
 * - GET /api/v2/quality/rating/{session_id} - 获取评分
 * - POST /api/v2/quality/rating/{session_id}/expert - 提交专家评审
 * - GET /api/v2/quality/fewshot-eligible - 获取Few-Shot合格会话
 * - GET /api/v2/quality/stats - 获取评分统计
 * 
 * @version 2.0.0
 * @date 2025-12-31
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\QualityRatingController;

Route::prefix('v2/quality')->middleware('jwt.auth')->group(function () {
    // 提交三轨评分
    Route::post('/rating', [QualityRatingController::class, 'submitRating']);
    
    // 获取会话评分
    Route::get('/rating/{session_id}', [QualityRatingController::class, 'getRating']);
    
    // 检查会话Few-Shot资格（带详细原因）
    Route::get('/rating/{session_id}/eligibility', [QualityRatingController::class, 'checkEligibility']);
    
    // 提交专家评审
    Route::post('/rating/{session_id}/expert', [QualityRatingController::class, 'submitExpertReview']);
    
    // 获取用户冷启动状态
    Route::get('/cold-start-status', [QualityRatingController::class, 'getColdStartStatus']);
    
    // 获取Few-Shot合格会话列表（管理员）
    Route::get('/fewshot-eligible', [QualityRatingController::class, 'getFewShotEligible']);
    
    // 获取Few-Shot池统计（管理员）
    Route::get('/fewshot-pool-stats', [QualityRatingController::class, 'getFewShotPoolStats']);
    
    // 获取评分统计（管理员）
    Route::get('/stats', [QualityRatingController::class, 'getStats']);
});
