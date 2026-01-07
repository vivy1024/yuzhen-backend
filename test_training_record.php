<?php

/**
 * 训练记录功能手动测试脚本
 * 
 * 运行方式：docker exec fitness_php_v2 php test_training_record.php
 * 
 * @version 1.0.0
 * @date 2025-12-19
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserProfile;

echo "=== 训练记录功能测试 ===\n\n";

// 1. 查找或创建测试用户
echo "1. 查找测试用户...\n";
$user = User::where('email', 'vivy@buildbudy.com')->first();

if (!$user) {
    echo "   ❌ 未找到测试用户\n";
    exit(1);
}

echo "   ✅ 找到用户: {$user->name} (ID: {$user->id})\n\n";

// 2. 查找或创建用户档案
echo "2. 查找用户档案...\n";
$userProfile = UserProfile::where('user_id', $user->id)->first();

if (!$userProfile) {
    echo "   创建新的用户档案...\n";
    $userProfile = UserProfile::create([
        'user_id' => $user->id,
        'basic_info' => [
            'age' => 25,
            'gender' => '男',
            'height' => 175,
            'weight' => 70,
            'body_fat_percentage' => 15,
        ],
        'fitness_goals' => [
            'primary_goal' => '增肌',
        ],
        'training_preferences' => [
            'training_split' => '推拉腿',
        ],
        'health_status' => [],
        'nutrition_profile' => [],
    ]);
    echo "   ✅ 用户档案已创建\n\n";
} else {
    echo "   ✅ 找到用户档案 (ID: {$userProfile->id})\n\n";
}

// 3. 测试记录训练数据
echo "3. 测试记录训练数据...\n";

// 记录深蹲训练
echo "   记录深蹲: 100kg × 5次\n";
$squatProgress = $userProfile->recordStrengthProgress('squat', 100, 5);
echo "   ✅ 估算1RM: {$squatProgress['current_1rm']}kg\n";
echo "   ✅ 力量水平: {$squatProgress['strength_level']}\n";
echo "   ✅ 历史记录数: " . count($squatProgress['history']) . "\n\n";

// 记录卧推训练
echo "   记录卧推: 80kg × 5次\n";
$benchProgress = $userProfile->recordStrengthProgress('bench_press', 80, 5);
echo "   ✅ 估算1RM: {$benchProgress['current_1rm']}kg\n";
echo "   ✅ 力量水平: {$benchProgress['strength_level']}\n\n";

// 记录硬拉训练
echo "   记录硬拉: 120kg × 5次\n";
$deadliftProgress = $userProfile->recordStrengthProgress('deadlift', 120, 5);
echo "   ✅ 估算1RM: {$deadliftProgress['current_1rm']}kg\n";
echo "   ✅ 力量水平: {$deadliftProgress['strength_level']}\n\n";

// 4. 测试获取力量进步数据
echo "4. 测试获取力量进步数据...\n";

$squatData = $userProfile->getStrengthProgress('squat');
echo "   深蹲当前1RM: {$squatData['current_1rm']}kg\n";
echo "   深蹲力量水平: {$squatData['strength_level']}\n";
echo "   深蹲历史记录数: " . count($squatData['history']) . "\n\n";

// 5. 测试获取所有当前1RM
echo "5. 测试获取所有当前1RM...\n";
$all1RMs = $userProfile->getAllCurrent1RMs();
foreach ($all1RMs as $exercise => $oneRM) {
    echo "   {$exercise}: {$oneRM}kg\n";
}
echo "\n";

// 6. 测试获取整体力量水平
echo "6. 测试获取整体力量水平...\n";
$overallLevel = $userProfile->getOverallStrengthLevel();
echo "   整体力量水平: {$overallLevel}\n\n";

// 7. 测试1RM估算公式
echo "7. 测试1RM估算公式...\n";
$testCases = [
    ['weight' => 100, 'reps' => 1, 'expected' => 100],
    ['weight' => 100, 'reps' => 5, 'expected' => 116.7],
    ['weight' => 80, 'reps' => 10, 'expected' => 106.7],
];

foreach ($testCases as $case) {
    $progress = $userProfile->recordStrengthProgress('test_exercise', $case['weight'], $case['reps']);
    $actual = $progress['current_1rm'];
    $match = abs($actual - $case['expected']) < 0.1 ? '✅' : '❌';
    echo "   {$match} {$case['weight']}kg × {$case['reps']}次 → 1RM: {$actual}kg (预期: {$case['expected']}kg)\n";
}
echo "\n";

// 8. 显示完整的力量进步数据
echo "8. 完整的力量进步数据:\n";
$strengthProgress = $userProfile->strength_progress;
echo json_encode($strengthProgress, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== 测试完成 ===\n";
