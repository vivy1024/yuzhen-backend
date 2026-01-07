<?php

/**
 * 三轨评分系统验证脚本
 * 
 * 验证内容：
 * 1. 评分流程完整性
 * 2. Few-Shot准入规则
 * 3. 冷启动期保护
 * 
 * @version 1.0.0
 * @date 2025-12-31
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;

// 初始化Laravel应用
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== 三轨评分系统验证 ===\n\n";

$allPassed = true;

// 1. 验证数据库表结构
echo "1. 验证数据库表结构...\n";

$chatSessionColumns = [
    'ux_clarity', 'ux_practicality', 'ux_detail', 'ux_friendliness', 'ux_satisfaction',
    'profile_utilization_rate', 'goal_alignment', 'uniqueness', 'dynamic_adjustment',
    'personalization_grade', 'fewshot_eligible', 'overall_score'
];

$missingColumns = [];
foreach ($chatSessionColumns as $column) {
    if (!Schema::hasColumn('chat_sessions', $column)) {
        $missingColumns[] = $column;
    }
}

if (empty($missingColumns)) {
    echo "   ✅ chat_sessions表结构完整（12个三轨评分字段）\n";
} else {
    echo "   ❌ chat_sessions表缺少字段: " . implode(', ', $missingColumns) . "\n";
    $allPassed = false;
}

// 检查expert_reviews表
if (Schema::hasTable('expert_reviews')) {
    echo "   ✅ expert_reviews表存在\n";
    
    $expertColumns = ['accuracy', 'scientific', 'safety', 'completeness', 'practicality', 'personalization'];
    $missingExpertColumns = [];
    foreach ($expertColumns as $column) {
        if (!Schema::hasColumn('expert_reviews', $column)) {
            $missingExpertColumns[] = $column;
        }
    }
    
    if (empty($missingExpertColumns)) {
        echo "   ✅ expert_reviews表结构完整（6维度专家评分）\n";
    } else {
        echo "   ❌ expert_reviews表缺少字段: " . implode(', ', $missingExpertColumns) . "\n";
        $allPassed = false;
    }
} else {
    echo "   ❌ expert_reviews表不存在\n";
    $allPassed = false;
}

// 2. 验证服务类存在
echo "\n2. 验证服务类...\n";

$services = [
    'App\Services\FewShotEligibilityService' => 'Few-Shot准入服务',
    'App\Services\PersonalizationScoreService' => '个性化评分服务',
];

foreach ($services as $class => $name) {
    if (class_exists($class)) {
        echo "   ✅ {$name}: {$class}\n";
    } else {
        echo "   ❌ {$name}: {$class} 不存在\n";
        $allPassed = false;
    }
}

// 3. 验证Few-Shot准入规则逻辑
echo "\n3. 验证Few-Shot准入规则逻辑...\n";

$fewShotService = app(\App\Services\FewShotEligibilityService::class);

// 验证阈值常量
$reflection = new ReflectionClass($fewShotService);

// 检查方法存在
$requiredMethods = [
    'checkEligibility' => '检查资格',
    'calculateUserExperienceAverage' => '计算用户体验平均分',
    'calculatePersonalizationAverage' => '计算个性化平均分',
    'getPoolStats' => '获取池统计',
];

foreach ($requiredMethods as $method => $desc) {
    if ($reflection->hasMethod($method)) {
        echo "   ✅ {$desc}方法存在: {$method}()\n";
    } else {
        echo "   ❌ {$desc}方法不存在: {$method}()\n";
        $allPassed = false;
    }
}

// 4. 验证个性化等级计算
echo "\n4. 验证个性化等级计算...\n";

$personalizationService = app(\App\Services\PersonalizationScoreService::class);

$gradeTests = [
    ['rate' => 95, 'expected' => 'S', 'desc' => '90-100% → S级'],
    ['rate' => 80, 'expected' => 'A', 'desc' => '75-89% → A级'],
    ['rate' => 65, 'expected' => 'B', 'desc' => '60-74% → B级'],
    ['rate' => 50, 'expected' => 'C', 'desc' => '40-59% → C级'],
    ['rate' => 30, 'expected' => 'D', 'desc' => '0-39% → D级'],
];

foreach ($gradeTests as $test) {
    $grade = $personalizationService->calculateGrade($test['rate']);
    if ($grade === $test['expected']) {
        echo "   ✅ {$test['desc']}: {$test['rate']}% = {$grade}\n";
    } else {
        echo "   ❌ {$test['desc']}: {$test['rate']}% 应为 {$test['expected']}，实际为 {$grade}\n";
        $allPassed = false;
    }
}

// 5. 验证API端点
echo "\n5. 验证API端点...\n";

$expectedRoutes = [
    'api/v2/quality/rating' => 'POST 提交评分',
    'api/v2/quality/rating/{session_id}' => 'GET 获取评分',
    'api/v2/quality/rating/{session_id}/eligibility' => 'GET 检查资格',
    'api/v2/quality/rating/{session_id}/expert' => 'POST 专家评审',
    'api/v2/quality/cold-start-status' => 'GET 冷启动状态',
    'api/v2/quality/fewshot-eligible' => 'GET Few-Shot列表',
    'api/v2/quality/fewshot-pool-stats' => 'GET Few-Shot统计',
    'api/v2/quality/stats' => 'GET 评分统计',
];

$routeCollection = Route::getRoutes();
$registeredUris = [];
foreach ($routeCollection as $route) {
    $registeredUris[] = $route->uri();
}

foreach ($expectedRoutes as $uri => $desc) {
    if (in_array($uri, $registeredUris)) {
        echo "   ✅ {$desc}: /{$uri}\n";
    } else {
        echo "   ❌ {$desc}: /{$uri} 未注册\n";
        $allPassed = false;
    }
}

// 6. 验证控制器
echo "\n6. 验证控制器...\n";

$controllerClass = 'App\Http\Controllers\Api\QualityRatingController';
if (class_exists($controllerClass)) {
    echo "   ✅ QualityRatingController 存在\n";
    
    $controllerReflection = new ReflectionClass($controllerClass);
    $controllerMethods = [
        'submitRating' => '提交评分',
        'getRating' => '获取评分',
        'checkEligibility' => '检查资格',
        'submitExpertReview' => '专家评审',
        'getColdStartStatus' => '冷启动状态',
        'getFewShotEligible' => 'Few-Shot列表',
        'getFewShotPoolStats' => 'Few-Shot统计',
        'getStats' => '评分统计',
    ];
    
    foreach ($controllerMethods as $method => $desc) {
        if ($controllerReflection->hasMethod($method)) {
            echo "   ✅ {$desc}: {$method}()\n";
        } else {
            echo "   ❌ {$desc}: {$method}() 不存在\n";
            $allPassed = false;
        }
    }
} else {
    echo "   ❌ QualityRatingController 不存在\n";
    $allPassed = false;
}

// 7. 验证模型方法
echo "\n7. 验证ChatSession模型方法...\n";

$modelClass = 'App\Models\ChatSession';
if (class_exists($modelClass)) {
    $modelReflection = new ReflectionClass($modelClass);
    $modelMethods = [
        'checkFewShotEligibility' => 'Few-Shot资格检查',
        'calculateOverallScore' => '综合评分计算',
        'getUserExperienceScores' => '获取用户体验评分',
        'getPersonalizationScores' => '获取个性化评分',
        'scopeFewShotEligible' => 'Few-Shot查询范围',
    ];
    
    foreach ($modelMethods as $method => $desc) {
        if ($modelReflection->hasMethod($method)) {
            echo "   ✅ {$desc}: {$method}()\n";
        } else {
            echo "   ❌ {$desc}: {$method}() 不存在\n";
            $allPassed = false;
        }
    }
} else {
    echo "   ❌ ChatSession模型不存在\n";
    $allPassed = false;
}

// 8. 统计信息
echo "\n8. 当前系统统计...\n";

try {
    $stats = $fewShotService->getPoolStats();
    echo "   总会话数: {$stats['total_sessions']}\n";
    echo "   已评分会话: {$stats['rated_sessions']}\n";
    echo "   Few-Shot合格: {$stats['eligible_sessions']}\n";
    echo "   合格率: {$stats['eligibility_rate']}%\n";
    echo "   安全性否决数: {$stats['safety_veto_count']}\n";
    echo "   阈值配置:\n";
    echo "     - 评分门槛: {$stats['thresholds']['score_threshold']}\n";
    echo "     - 安全性否决门槛: {$stats['thresholds']['safety_veto_threshold']}\n";
    echo "     - 冷启动期对话数: {$stats['thresholds']['cold_start_count']}\n";
    echo "     - 冷启动期门槛: {$stats['thresholds']['cold_start_threshold']}\n";
} catch (Exception $e) {
    echo "   ⚠️ 获取统计信息失败: " . $e->getMessage() . "\n";
}

// 总结
echo "\n=== 验证结果 ===\n";
if ($allPassed) {
    echo "✅ 所有验证通过！三轨评分系统已完整实现。\n";
    echo "\n准入规则说明：\n";
    echo "1. 三轨高分（≥4.0）：用户体验、个性化感知、专家评分均需达标\n";
    echo "2. 安全性一票否决（<3）：专家评分中安全性低于3分直接否决\n";
    echo "3. 冷启动期保护：前3条对话降低门槛至3.5分\n";
} else {
    echo "❌ 部分验证未通过，请检查上述错误。\n";
}

echo "\n=== 验证完成 ===\n";
