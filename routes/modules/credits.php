<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Credits\Controllers\CreditController;
use App\Modules\Credits\Controllers\InviteController;
use App\Modules\Credits\Controllers\InternalCreditController;

/**
 * Credits Module Routes (v2)
 *
 * 积分系统 v2 路由
 *
 * 用户端前缀: /api/credits/v2
 * 内部端前缀: /api/internal/credits/v2
 *
 * @version v2.0.0
 */

// ========== 用户端路由（需要 jwt.auth）==========
Route::prefix('credits/v2')->middleware(['jwt.auth'])->group(function () {

    // 查询余额
    // GET /api/credits/v2/balance
    Route::get('/balance', [CreditController::class, 'balance']);

    // 查询流水（分页）
    // GET /api/credits/v2/transactions?page=1&per_page=15&type=spend
    Route::get('/transactions', [CreditController::class, 'transactions']);

    // 每日签到
    // POST /api/credits/v2/checkin
    Route::post('/checkin', [CreditController::class, 'checkin']);

    // ===== 邀请好友 =====
    // GET /api/credits/v2/invite/code — 获取我的邀请码
    Route::get('/invite/code', [InviteController::class, 'code']);

    // GET /api/credits/v2/invite/stats — 邀请统计
    Route::get('/invite/stats', [InviteController::class, 'stats']);
});

// ========== 内部 API（internal.api 中间件）==========
Route::prefix('internal/credits/v2')->middleware(['internal.api'])->group(function () {

    // AI 对话后扣减积分
    // POST /api/internal/credits/v2/deduct
    // body: { user_id, model, input_tokens, output_tokens, session_id }
    Route::post('/deduct', [InternalCreditController::class, 'deduct']);
});
