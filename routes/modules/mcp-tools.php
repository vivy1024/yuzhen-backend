<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MCPToolsController;

/**
 * MCP Tools API Routes
 *
 * MCP工具API路由 - 代理前端请求到MCO服务器
 *
 * 架构: 前端(9000) → 后端API网关(8000) → MCO服务器(8001)
 *
 * @version 1.0.0
 * @created 2025-11-03
 */

// 通用MCP工具调用
Route::post('/tools/execute', [MCPToolsController::class, 'executeGeneric']);

// MCP专用工具API
Route::prefix('mcp/tools')->group(function () {

    // AI生成个性化训练计划
    Route::post('/design-personalized-program-v2', [MCPToolsController::class, 'designPersonalizedProgram']);

    // AI计算训练重量推荐
    Route::post('/calculate-training-weights', [MCPToolsController::class, 'calculateTrainingWeights']);

    // 推荐RPE范围
    Route::post('/recommend-rpe-range', [MCPToolsController::class, 'recommendRPERange']);

    // 搜索动作
    Route::post('/search-exercises', [MCPToolsController::class, 'searchExercises']);

    // 获取训练计划模板
    Route::get('/get-training-program-template', function () {
        // TODO: 实现模板系统
        return response()->json([
            'code' => 200,
            'msg' => '功能开发中',
            'data' => null,
        ]);
    });

    // 列出所有模板
    Route::get('/training-program-templates', function () {
        // TODO: 实现模板列表
        return response()->json([
            'code' => 200,
            'msg' => '功能开发中',
            'data' => [],
        ]);
    });
});
