<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/**
 * API Routes
 * 
 * 主路由入口，加载各模块路由
 * 
 * @version 2.0.0
 */

/*
|--------------------------------------------------------------------------
| 健康检查
|--------------------------------------------------------------------------
*/
Route::get('/health', [\App\Http\Controllers\HealthCheckController::class, 'index']);
Route::get('/health/components', [\App\Http\Controllers\HealthCheckController::class, 'components']);
Route::get('/health/cors', [\App\Http\Controllers\HealthCheckController::class, 'cors']);

/*
|--------------------------------------------------------------------------
| 用户信息（需要认证）
|--------------------------------------------------------------------------
*/
Route::middleware('jwt.auth')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| 模块路由
|--------------------------------------------------------------------------
*/

// Exercise模块
require __DIR__.'/modules/exercise.php';

// User模块 ✅
require __DIR__.'/modules/user.php';

// Auth模块 ✅
require __DIR__.'/modules/auth.php';

// [REMOVED] 旧版训练路由已废弃，前端统一使用 /api/training/plans/*
// 文件保留在 routes/modules/training.php 以备回滚
// require __DIR__.'/modules/training.php';

// Training Record模块（力量进步追踪）✅
require __DIR__.'/modules/training-record.php';

// Training Log模块（闭环学习系统）✅
require __DIR__.'/modules/training-log.php';

// Personal Best模块（个人最佳记录）✅
require __DIR__.'/modules/personal-best.php';

// Social Login模块 ✅
require __DIR__.'/modules/social.php';

// Membership模块 ✅
require __DIR__.'/modules/membership.php';

// Usage模块（用量管理）✅
require __DIR__.'/modules/usage.php';

// Credit模块（积分管理）✅
require __DIR__.'/modules/credit.php';

// Credits v2模块（积分系统重构）✅
require __DIR__.'/modules/credits.php';

// Internal API（MCP/CrewAI访问）✅
require __DIR__.'/internal.php';

// MCP Tools API（前端MCP工具调用代理）✅
require __DIR__.'/modules/mcp-tools.php';

// Quality Rating模块（三轨评分系统）✅
require __DIR__.'/modules/quality-rating.php';

// Personalization Grading模块（个性化分级系统）✅
Route::prefix('v2/personalization')->group(function () {
    require __DIR__.'/modules/personalization_grading.php';
});

// Chat Topic模块（AI聊天话题管理）✅
require __DIR__.'/modules/chat-topic.php';

// Training Plan模块（训练计划导入）✅
require __DIR__.'/modules/training-plan.php';

// Food模块（食物库）✅
require __DIR__.'/modules/food.php';

// Admin模块（管理员后台）✅
require __DIR__.'/modules/admin.php';

// Progress模块（进度追踪）✅
require __DIR__.'/modules/progress.php';

// Feedback模块（用户反馈）✅
require __DIR__.'/modules/feedback.php';

// Help模块（帮助中心）✅
require __DIR__.'/modules/help.php';

// Knowledge模块（知识库）✅
require __DIR__.'/modules/knowledge.php';

// Push模块（推送通知）✅
require __DIR__.'/modules/push.php';

// User Settings模块（用户设置与账号安全）✅
require __DIR__.'/modules/user-settings.php';

// Calculator模块（健身计算器，无需认证）✅
require __DIR__.'/modules/calculator.php';

// Consent模块（协议同意记录）✅
require __DIR__.'/modules/consent.php';

// AI代理模块已移至 routes/web.php（无/api前缀）
// require __DIR__.'/modules/ai-proxy.php';

/*
|--------------------------------------------------------------------------
| AI代理路由（直接在api.php中定义，避免CORS问题）
|--------------------------------------------------------------------------
| 前端请求: /api/ai/v1/chat/stream
| 代理到: DAML-RAG服务
| 
| 注意：使用api中间件组，自动处理CORS，无需CSRF验证
*/
Route::prefix('ai')->group(function () {
    // 流式/非流式聊天接口（需要JWT认证 + 配额检查 + Internal JWT转发）
    Route::middleware(['jwt.auth', 'quota.check', 'internal.jwt.forward'])->group(function () {
        Route::post('/v1/chat/stream', [\App\Http\Controllers\AiProxyController::class, 'streamChat']);
        Route::post('/v1/chat', [\App\Http\Controllers\AiProxyController::class, 'chat']);
    });
    
    // 线程管理（需要JWT认证）
    Route::middleware(['jwt.auth'])->group(function () {
        Route::post('/v1/thread/create', [\App\Http\Controllers\Api\ThreadController::class, 'create']);
        Route::get('/v1/thread/list', [\App\Http\Controllers\Api\ThreadController::class, 'list']);
        Route::delete('/v1/thread/{threadId}', [\App\Http\Controllers\Api\ThreadController::class, 'delete']);
    });
    
    // HITL 审批回调（需要JWT认证 + Internal JWT转发）
    Route::middleware(['jwt.auth', 'internal.jwt.forward'])->group(function () {
        Route::post('/v1/approval/respond', [\App\Http\Controllers\Api\ApprovalController::class, 'respond']);
        Route::get('/v1/approval/pending', [\App\Http\Controllers\Api\ApprovalController::class, 'pending']);
    });
    
    // 用户预热接口（需要JWT认证 + Internal JWT转发，不需要配额检查）
    Route::middleware(['jwt.auth', 'internal.jwt.forward'])->group(function () {
        Route::post('/v1/user/warmup', [\App\Http\Controllers\AiProxyController::class, 'warmup']);
        Route::get('/v1/user/warmup/status/{userId}', [\App\Http\Controllers\AiProxyController::class, 'warmupStatus']);
    });
    
    // 健康检查（无需认证）
    Route::get('/health', [\App\Http\Controllers\AiProxyController::class, 'health']);
});
