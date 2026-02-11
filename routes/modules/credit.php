<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CreditController;

/**
 * Credit Module Routes
 * 
 * 积分管理模块路由
 * 
 * 前缀: /api/credits
 * 
 * @version v1.0.0
 * @date 2026-02-05
 * @author 薛小川
 * @requirements 4.1, 3.4, 3.5
 */

Route::prefix('credits')->group(function () {
    
    // ========== 需要认证的路由 ==========
    Route::middleware(['jwt.auth'])->group(function () {
        
        // 获取积分余额
        // GET /api/credits/balance
        // 返回：daily_quota, daily_consumed, remaining, total_consumed, membership_tier, is_mvp_phase, low_balance_warning, last_reset
        // @requirements 4.1, 4.2, 4.3, 4.4, 4.5
        Route::get('/balance', [CreditController::class, 'getBalance']);
        
        // 获取积分流水历史
        // GET /api/credits/history
        // 参数：page, per_page, mode, start_date, end_date
        // 返回：transactions, pagination, summary
        // @requirements 3.4, 3.5
        Route::get('/history', [CreditController::class, 'getHistory']);
        
        // 获取积分消耗统计
        // GET /api/credits/stats
        // 参数：period (today/week/month/all)
        // 返回：summary, by_mode, by_template, daily_trend
        // @requirements 3.5
        Route::get('/stats', [CreditController::class, 'getStats']);
        
    });
    
});
