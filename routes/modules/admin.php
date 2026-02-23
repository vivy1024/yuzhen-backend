<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Membership\Controllers\AdminOrderController;
use App\Modules\Admin\Controllers\AdminSessionController;
use App\Modules\Admin\Controllers\AdminUserController;
use App\Modules\Admin\Controllers\AdminFeedbackController;
use App\Modules\Admin\Controllers\MetricsProxyController;
use App\Modules\Admin\Controllers\UserCreditsController;
use App\Modules\Admin\Controllers\AdminKpiController;
use App\Http\Controllers\Admin\DataMigrationController;
use App\Http\Controllers\Api\Admin\MetricsController as AdminMetricsController;

/**
 * Admin Module Routes
 * 
 * 管理员模块路由
 * 
 * 前缀: /api/admin
 * 中间件: jwt.auth + admin
 */

// 管理员身份验证（仅需jwt.auth，用于前端路由守卫）
Route::prefix('admin')->middleware(['jwt.auth'])->group(function () {
    Route::get('/verify', function () {
        $user = auth()->user();
        return response()->json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'is_admin' => $user->role === 'admin',
                'role' => $user->role ?? 'user',
            ]
        ]);
    });
});

Route::prefix('admin')->middleware(['jwt.auth', 'admin'])->group(function () {
    
    // ========== 订单管理 ==========
    Route::prefix('orders')->group(function () {
        // 获取订单统计
        Route::get('/stats', [AdminOrderController::class, 'stats']);
        
        // 获取待审核订单
        Route::get('/pending', [AdminOrderController::class, 'pendingOrders']);
        
        // 获取所有订单
        Route::get('/', [AdminOrderController::class, 'index']);
        
        // 获取订单详情
        Route::get('/{id}', [AdminOrderController::class, 'show']);
        
        // 审核通过
        Route::post('/{id}/approve', [AdminOrderController::class, 'approve']);
        
        // 审核拒绝
        Route::post('/{id}/reject', [AdminOrderController::class, 'reject']);
    });
    
    // ========== 反馈管理 ==========
    Route::prefix('feedback')->group(function () {
        // 获取反馈统计
        Route::get('/stats', [AdminFeedbackController::class, 'stats']);
        
        // 获取所有反馈
        Route::get('/', [AdminFeedbackController::class, 'index']);
        
        // 获取反馈详情
        Route::get('/{id}', [AdminFeedbackController::class, 'show']);
        
        // 回复反馈
        Route::put('/{id}/reply', [AdminFeedbackController::class, 'reply']);
        
        // 更新反馈状态
        Route::put('/{id}/status', [AdminFeedbackController::class, 'updateStatus']);
        
        // 批量更新状态
        Route::put('/batch-status', [AdminFeedbackController::class, 'batchUpdateStatus']);
        
        // 删除反馈
        Route::delete('/{id}', [AdminFeedbackController::class, 'destroy']);
    });
    
    // ========== 会话管理（三轨评分） ==========
    Route::prefix('sessions')->group(function () {
        // 获取待评审会话
        Route::get('/pending-review', [AdminSessionController::class, 'pendingReview']);
        
        // 获取已评审会话
        Route::get('/reviewed', [AdminSessionController::class, 'reviewed']);
    });
    
    // ========== 用户管理 ==========
    Route::prefix('users')->group(function () {
        // 获取用户列表
        Route::get('/', [AdminUserController::class, 'index']);
        
        // 获取用户详情
        Route::get('/{id}', [AdminUserController::class, 'show']);
        
        // 更新用户角色
        Route::put('/{id}/role', [AdminUserController::class, 'updateRole']);
        
        // 获取用户用量统计
        Route::get('/{userId}/usage', [UserCreditsController::class, 'getUserUsage']);
        
        // 添加额外次数（打赏奖励）
        Route::post('/{userId}/credits', [UserCreditsController::class, 'addCredits']);
    });
    
    // ========== 用户额外次数管理（批量操作） ==========
    Route::post('/users/credits/batch', [UserCreditsController::class, 'batchAddCredits']);
    Route::get('/credits/config', [UserCreditsController::class, 'getCreditsConfig']);
    
    // ========== 监控指标代理 ==========
    Route::prefix('metrics')->group(function () {
        // Prometheus即时查询
        Route::get('/query', [MetricsProxyController::class, 'query']);
        
        // Prometheus范围查询
        Route::get('/query_range', [MetricsProxyController::class, 'queryRange']);
        
        // 批量查询
        Route::post('/batch', [MetricsProxyController::class, 'batchQuery']);
        
        // DAML-RAG健康状态
        Route::get('/daml-rag/health', [MetricsProxyController::class, 'damlRagHealth']);
        
        // DAML-RAG系统指标
        Route::get('/daml-rag/metrics', [MetricsProxyController::class, 'damlRagMetrics']);
        
        // 流式监控统计
        Route::get('/daml-rag/streaming', [MetricsProxyController::class, 'damlRagStreaming']);
        
        // 流式会话记录
        Route::get('/daml-rag/streaming/recent', [MetricsProxyController::class, 'damlRagStreamingRecent']);
        
        // DAML-RAG日志
        Route::get('/daml-rag/logs', [MetricsProxyController::class, 'damlRagLogs']);
        
        // Loki日志查询
        Route::get('/loki/query', [MetricsProxyController::class, 'lokiQuery']);
        Route::get('/loki/labels', [MetricsProxyController::class, 'lokiLabels']);
        
        // Prometheus原始指标
        Route::get('/prometheus/raw', [MetricsProxyController::class, 'prometheusRaw']);

        // ========== 统一仪表盘聚合API（MySQL + Redis缓存） ==========
        Route::prefix('dashboard')->group(function () {
            Route::get('/system-overview', [AdminMetricsController::class, 'systemOverview']);
            Route::get('/model-comparison', [AdminMetricsController::class, 'modelComparison']);
            Route::get('/mode-comparison', [AdminMetricsController::class, 'modeComparison']);
            Route::get('/tool-usage', [AdminMetricsController::class, 'toolUsage']);
            Route::get('/user-consumption', [AdminMetricsController::class, 'userConsumption']);
            Route::get('/quality-trend', [AdminMetricsController::class, 'qualityTrend']);
        });
    });
    
    // ========== 运营 KPI ==========
    Route::prefix('kpi')->group(function () {
        Route::get('/overview', [AdminKpiController::class, 'overview']);
        Route::get('/growth', [AdminKpiController::class, 'growth']);
        Route::get('/activity', [AdminKpiController::class, 'activity']);
        Route::get('/retention', [AdminKpiController::class, 'retention']);
    });

    // ========== 数据迁移（临时） ==========
    Route::prefix('migrate')->group(function () {
        // 肌肉字段迁移预览
        Route::get('/muscles/preview', [DataMigrationController::class, 'previewMuscleMigration']);
        
        // 执行肌肉字段迁移
        Route::post('/muscles/execute', [DataMigrationController::class, 'executeMuscleMigration']);
        
        // 验证迁移结果
        Route::get('/muscles/verify', [DataMigrationController::class, 'verifyMigration']);
    });
    
});
