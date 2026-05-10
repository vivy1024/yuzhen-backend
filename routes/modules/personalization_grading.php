<?php

/**
 * Personalization Grading Module - 个性化分级API
 *
 * 功能：
 * 1. 档案利用率计算 (0-100%)
 * 2. 个性化等级划分 (S/A/B/C/D)
 * 3. 升级提示生成
 * 4. 升级收益说明
 * 5. B端演示数据收集
 *
 * @author BUILD_BODY Team
 * @version 1.0.0
 * @created 2025-12-31
 * @requirements 6.1, 6.2, 6.3, 6.4, 6.5
 */

use Illuminate\Http\Request;
use App\Services\PersonalizationGradingService;

/**
 * 获取当前用户的个性化分级报告
 *
 * GET /api/v2/personalization/report
 * @requirements 6.1, 6.2
 */
Route::get('/report', function (Request $request) {
    try {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'msg' => '未登录'
            ], 401);
        }

        $service = new PersonalizationGradingService();
        $report = $service->getUserGradingReport($user);

        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => $report
        ]);

    } catch (\Exception $e) {
        \Log::error('获取个性化分级报告失败: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

        return response()->json([
            'code' => 500,
            'msg' => '服务暂时不可用，请稍后重试'
        ], 500);
    }
})->middleware('jwt.auth');

/**
 * 计算档案利用率
 *
 * POST /api/v2/personalization/utilization
 * @requirements 6.1
 */
Route::post('/utilization', function (Request $request) {
    try {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'msg' => '未登录'
            ], 401);
        }

        $sessionMetadata = $request->input('session_metadata', []);

        $service = new PersonalizationGradingService();
        $utilizationData = $service->calculateProfileUtilization($user, $sessionMetadata);

        return response()->json([
            'code' => 200,
            'msg' => '计算成功',
            'data' => $utilizationData
        ]);

    } catch (\Exception $e) {
        \Log::error('计算档案利用率失败: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

        return response()->json([
            'code' => 500,
            'msg' => '服务暂时不可用，请稍后重试'
        ], 500);
    }
})->middleware('jwt.auth');

/**
 * 获取个性化等级信息
 *
 * GET /api/v2/personalization/grade/{grade}
 * @requirements 6.2
 */
Route::get('/grade/{grade}', function (Request $request, $grade) {
    try {
        $validGrades = ['S', 'A', 'B', 'C', 'D'];
        $grade = strtoupper($grade);

        if (!in_array($grade, $validGrades)) {
            return response()->json([
                'code' => 400,
                'msg' => '无效的等级，有效值为: S, A, B, C, D'
            ], 400);
        }

        $service = new PersonalizationGradingService();
        $gradeInfo = $service->getGradeInfo($grade);

        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => [
                'grade' => $grade,
                ...$gradeInfo
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('获取等级信息失败: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

        return response()->json([
            'code' => 500,
            'msg' => '服务暂时不可用，请稍后重试'
        ], 500);
    }
})->middleware('jwt.auth'); // SEC-6: 添加认证

/**
 * 获取所有等级信息
 *
 * GET /api/v2/personalization/grades
 * @requirements 6.2
 */
Route::get('/grades', function (Request $request) {
    try {
        $service = new PersonalizationGradingService();
        
        $grades = [];
        foreach (['S', 'A', 'B', 'C', 'D'] as $grade) {
            $grades[$grade] = $service->getGradeInfo($grade);
        }

        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => $grades
        ]);

    } catch (\Exception $e) {
        \Log::error('获取等级列表失败: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

        return response()->json([
            'code' => 500,
            'msg' => '服务暂时不可用，请稍后重试'
        ], 500);
    }
})->middleware('jwt.auth'); // SEC-6: 添加认证

/**
 * 获取升级提示
 *
 * GET /api/v2/personalization/upgrade-prompt
 * @requirements 6.3
 */
Route::get('/upgrade-prompt', function (Request $request) {
    try {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'msg' => '未登录'
            ], 401);
        }

        $service = new PersonalizationGradingService();
        
        // 计算当前利用率
        $utilizationData = $service->calculateProfileUtilization($user);
        $utilizationRate = $utilizationData['utilization_rate'];
        
        // 获取当前会员等级
        $currentTier = $user->membership_tier ?? 'free';
        
        // 生成升级提示
        $upgradePrompt = $service->generateUpgradePrompt($user, $utilizationRate, $currentTier);

        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => $upgradePrompt
        ]);

    } catch (\Exception $e) {
        \Log::error('获取升级提示失败: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

        return response()->json([
            'code' => 500,
            'msg' => '服务暂时不可用，请稍后重试'
        ], 500);
    }
})->middleware('jwt.auth');

/**
 * 获取升级收益说明
 *
 * GET /api/v2/personalization/upgrade-benefits
 * @requirements 6.4
 */
Route::get('/upgrade-benefits', function (Request $request) {
    try {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'msg' => '未登录'
            ], 401);
        }

        $service = new PersonalizationGradingService();
        
        // 获取用户使用统计
        $usageCount = \App\Models\ChatSession::where('user_id', $user->id)->count();
        $avgPersonalization = \App\Models\ChatSession::where('user_id', $user->id)
            ->avg('profile_utilization_rate') ?? 0;
        
        // 生成升级收益说明
        $upgradeBenefits = $service->generateUpgradeBenefits($user, $usageCount, $avgPersonalization);

        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => $upgradeBenefits
        ]);

    } catch (\Exception $e) {
        \Log::error('获取升级收益说明失败: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

        return response()->json([
            'code' => 500,
            'msg' => '服务暂时不可用，请稍后重试'
        ], 500);
    }
})->middleware('jwt.auth');

/**
 * 获取B端演示数据
 *
 * GET /api/v2/personalization/demo-data
 * @requirements 6.5
 */
Route::get('/demo-data', function (Request $request) {
    try {
        // 验证管理员权限
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'code' => 403,
                'msg' => '权限不足，仅管理员可访问'
            ], 403);
        }

        $filters = [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        $service = new PersonalizationGradingService();
        $demoData = $service->collectDemoData($filters);

        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => $demoData
        ]);

    } catch (\Exception $e) {
        \Log::error('获取B端演示数据失败: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

        return response()->json([
            'code' => 500,
            'msg' => '服务暂时不可用，请稍后重试'
        ], 500);
    }
})->middleware('jwt.auth');

/**
 * 获取个性化统计概览（公开API，用于展示）
 *
 * GET /api/v2/personalization/stats-overview
 */
Route::get('/stats-overview', function (Request $request) {
    try {
        // 获取基础统计（不需要登录）
        $totalSessions = \App\Models\ChatSession::count();
        
        $gradeDistribution = \App\Models\ChatSession::selectRaw('personalization_grade, COUNT(*) as count')
            ->whereNotNull('personalization_grade')
            ->groupBy('personalization_grade')
            ->pluck('count', 'personalization_grade')
            ->toArray();

        $avgUtilization = \App\Models\ChatSession::whereNotNull('profile_utilization_rate')
            ->avg('profile_utilization_rate');

        $highPersonalizationCount = ($gradeDistribution['S'] ?? 0) + ($gradeDistribution['A'] ?? 0);
        $highPersonalizationRate = $totalSessions > 0 
            ? round($highPersonalizationCount / $totalSessions * 100, 1) 
            : 0;

        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => [
                'total_sessions' => $totalSessions,
                'avg_utilization_rate' => round($avgUtilization ?? 0, 1),
                'high_personalization_rate' => $highPersonalizationRate,
                'grade_distribution' => [
                    'S' => $gradeDistribution['S'] ?? 0,
                    'A' => $gradeDistribution['A'] ?? 0,
                    'B' => $gradeDistribution['B'] ?? 0,
                    'C' => $gradeDistribution['C'] ?? 0,
                    'D' => $gradeDistribution['D'] ?? 0,
                ],
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('获取统计概览失败: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

        return response()->json([
            'code' => 500,
            'msg' => '服务暂时不可用，请稍后重试'
        ], 500);
    }
})->middleware('jwt.auth'); // SEC-6: 添加认证（防止泄露运营数据）
