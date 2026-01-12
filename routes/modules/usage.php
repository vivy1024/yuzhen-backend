<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UsageController;

/**
 * Usage Module Routes
 * 
 * 用量管理模块路由
 * 
 * 前缀: /api/usage
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 4.1-4.4
 */

Route::prefix('usage')->group(function () {
    
    // ========== 需要认证的路由 ==========
    Route::middleware(['jwt.auth'])->group(function () {
        
        // 获取今日用量统计
        // GET /api/usage/today
        // 返回：dag_used, dag_limit, dag_remaining, agent_used, agent_limit, agent_remaining
        // @requirements 4.1, 4.3
        Route::get('/today', [UsageController::class, 'today']);
        
        // 获取额外额度余额
        // GET /api/usage/credits
        // 返回：dag_credits, agent_credits, total_credits
        // @requirements 4.2
        Route::get('/credits', [UsageController::class, 'credits']);
        
        // 检查是否可以执行查询
        // POST /api/usage/check
        // 参数：mode (dag|agent)
        // 返回：allowed, remaining, use_credits, message
        // @requirements 4.3
        Route::post('/check', [UsageController::class, 'check']);
        
        // 增加用量计数（通常由DAML-RAG服务调用）
        // POST /api/usage/increment
        // 参数：mode (dag|agent)
        // 返回：success, used_credits, new_count, remaining, message
        // @requirements 4.6
        Route::post('/increment', [UsageController::class, 'increment']);
        
        // 获取用量历史统计
        // GET /api/usage/history
        // 参数：days (默认30)
        // 返回：period_days, total_dag_queries, total_agent_queries, daily_stats
        Route::get('/history', [UsageController::class, 'history']);
        
    });
    
});
