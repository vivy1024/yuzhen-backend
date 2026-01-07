<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\TrainingLog;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * TrainingLog 单元测试
 * 
 * 测试训练日志模型的核心功能：
 * - 完成率计算 (Requirements: 6.3)
 * - RPE值验证 (Requirements: 6.2)
 * 
 * @version 1.0.0
 * @date 2025-12-26
 */
class TrainingLogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试完成率计算 - 完全完成
     * 
     * Requirements: 6.3 - 完成率 = 实际完成组数 / 计划组数
     */
    public function test_completion_rate_full_completion(): void
    {
        $log = new TrainingLog();
        $log->planned_exercises = [
            ['exercise_id' => 'squat', 'sets' => 4, 'reps' => 8],
            ['exercise_id' => 'bench', 'sets' => 3, 'reps' => 10],
        ];
        $log->actual_exercises = [
            ['exercise_id' => 'squat', 'completed_sets' => 4],
            ['exercise_id' => 'bench', 'completed_sets' => 3],
        ];

        $rate = $log->calculateCompletionRate();

        // 计划7组，完成7组，完成率应为1.0
        $this->assertEquals(1.0, $rate);
    }

    /**
     * 测试完成率计算 - 部分完成
     */
    public function test_completion_rate_partial_completion(): void
    {
        $log = new TrainingLog();
        $log->planned_exercises = [
            ['exercise_id' => 'squat', 'sets' => 4, 'reps' => 8],
            ['exercise_id' => 'bench', 'sets' => 4, 'reps' => 10],
        ];
        $log->actual_exercises = [
            ['exercise_id' => 'squat', 'completed_sets' => 3],
            ['exercise_id' => 'bench', 'completed_sets' => 3],
        ];

        $rate = $log->calculateCompletionRate();

        // 计划8组，完成6组，完成率应为0.75
        $this->assertEquals(0.75, $rate);
    }

    /**
     * 测试完成率计算 - 空计划
     */
    public function test_completion_rate_empty_planned(): void
    {
        $log = new TrainingLog();
        $log->planned_exercises = [];
        $log->actual_exercises = [
            ['exercise_id' => 'squat', 'completed_sets' => 4],
        ];

        $rate = $log->calculateCompletionRate();

        $this->assertEquals(0.0, $rate);
    }

    /**
     * 测试完成率计算 - 空实际
     */
    public function test_completion_rate_empty_actual(): void
    {
        $log = new TrainingLog();
        $log->planned_exercises = [
            ['exercise_id' => 'squat', 'sets' => 4, 'reps' => 8],
        ];
        $log->actual_exercises = [];

        $rate = $log->calculateCompletionRate();

        $this->assertEquals(0.0, $rate);
    }

    /**
     * 测试完成率计算 - 超额完成（应限制在1.0）
     */
    public function test_completion_rate_over_completion(): void
    {
        $log = new TrainingLog();
        $log->planned_exercises = [
            ['exercise_id' => 'squat', 'sets' => 3, 'reps' => 8],
        ];
        $log->actual_exercises = [
            ['exercise_id' => 'squat', 'completed_sets' => 5],
        ];

        $rate = $log->calculateCompletionRate();

        // 超额完成应限制在1.0
        $this->assertEquals(1.0, $rate);
    }

    /**
     * 测试获取计划总组数
     */
    public function test_get_planned_sets_count(): void
    {
        $log = new TrainingLog();
        $log->planned_exercises = [
            ['exercise_id' => 'squat', 'sets' => 4],
            ['exercise_id' => 'bench', 'sets' => 3],
            ['exercise_id' => 'deadlift', 'sets' => 3],
        ];

        $count = $log->getPlannedSetsCount();

        $this->assertEquals(10, $count);
    }

    /**
     * 测试获取实际完成组数
     */
    public function test_get_completed_sets_count(): void
    {
        $log = new TrainingLog();
        $log->actual_exercises = [
            ['exercise_id' => 'squat', 'completed_sets' => 4],
            ['exercise_id' => 'bench', 'completed_sets' => 2],
        ];

        $count = $log->getCompletedSetsCount();

        $this->assertEquals(6, $count);
    }

    /**
     * 测试RPE值验证 - 有效值
     * 
     * Requirements: 6.2 - RPE值必须在1-10范围内
     */
    public function test_rpe_validation_valid_values(): void
    {
        $this->assertTrue(TrainingLog::isValidRpe(1));
        $this->assertTrue(TrainingLog::isValidRpe(5));
        $this->assertTrue(TrainingLog::isValidRpe(10));
        $this->assertTrue(TrainingLog::isValidRpe(7.5));
        $this->assertTrue(TrainingLog::isValidRpe('8'));
        $this->assertTrue(TrainingLog::isValidRpe('9.5'));
    }

    /**
     * 测试RPE值验证 - 无效值（超出范围）
     * 
     * Requirements: 6.2 - 超出范围返回验证错误
     */
    public function test_rpe_validation_invalid_values(): void
    {
        $this->assertFalse(TrainingLog::isValidRpe(0));
        $this->assertFalse(TrainingLog::isValidRpe(0.5));
        $this->assertFalse(TrainingLog::isValidRpe(11));
        $this->assertFalse(TrainingLog::isValidRpe(-1));
        $this->assertFalse(TrainingLog::isValidRpe('abc'));
        $this->assertFalse(TrainingLog::isValidRpe(null));
    }

    /**
     * 测试RPE值验证方法返回错误信息
     */
    public function test_validate_rpe_returns_error_for_invalid(): void
    {
        // 小于1
        $error = TrainingLog::validateRpe(0.5);
        $this->assertNotNull($error);
        $this->assertEquals('rpe', $error['field']);
        $this->assertStringContainsString('不能小于1', $error['message']);

        // 大于10
        $error = TrainingLog::validateRpe(11);
        $this->assertNotNull($error);
        $this->assertStringContainsString('不能大于10', $error['message']);

        // 非数字
        $error = TrainingLog::validateRpe('abc');
        $this->assertNotNull($error);
        $this->assertStringContainsString('必须是数字', $error['message']);
    }

    /**
     * 测试RPE值验证方法 - null值应该通过（RPE是可选的）
     */
    public function test_validate_rpe_allows_null(): void
    {
        $error = TrainingLog::validateRpe(null);
        $this->assertNull($error);
    }

    /**
     * 测试RPE值规范化
     */
    public function test_normalize_rpe(): void
    {
        // 正常值
        $this->assertEquals(7.5, TrainingLog::normalizeRpe(7.5));
        
        // 小于1应规范化为1
        $this->assertEquals(1.0, TrainingLog::normalizeRpe(0.5));
        
        // 大于10应规范化为10
        $this->assertEquals(10.0, TrainingLog::normalizeRpe(12));
        
        // null应返回null
        $this->assertNull(TrainingLog::normalizeRpe(null));
        
        // 非数字应返回null
        $this->assertNull(TrainingLog::normalizeRpe('abc'));
    }

    /**
     * 测试平均RPE计算
     */
    public function test_calculate_avg_rpe(): void
    {
        $log = new TrainingLog();
        $log->actual_exercises = [
            ['exercise_id' => 'squat', 'completed_sets' => 4, 'rpe' => 8],
            ['exercise_id' => 'bench', 'completed_sets' => 3, 'rpe' => 7],
            ['exercise_id' => 'deadlift', 'completed_sets' => 3, 'rpe' => 9],
        ];

        $avgRpe = $log->calculateAvgRpe();

        // (8 + 7 + 9) / 3 = 8.0
        $this->assertEquals(8.0, $avgRpe);
    }

    /**
     * 测试平均RPE计算 - 部分动作没有RPE
     */
    public function test_calculate_avg_rpe_partial(): void
    {
        $log = new TrainingLog();
        $log->actual_exercises = [
            ['exercise_id' => 'squat', 'completed_sets' => 4, 'rpe' => 8],
            ['exercise_id' => 'bench', 'completed_sets' => 3], // 没有RPE
            ['exercise_id' => 'deadlift', 'completed_sets' => 3, 'rpe' => 6],
        ];

        $avgRpe = $log->calculateAvgRpe();

        // (8 + 6) / 2 = 7.0
        $this->assertEquals(7.0, $avgRpe);
    }

    /**
     * 测试平均RPE计算 - 空实际动作
     */
    public function test_calculate_avg_rpe_empty(): void
    {
        $log = new TrainingLog();
        $log->actual_exercises = [];

        $avgRpe = $log->calculateAvgRpe();

        $this->assertEquals(0.0, $avgRpe);
    }

    /**
     * 测试用户关联
     */
    public function test_user_relationship(): void
    {
        // 直接创建用户而不使用Factory
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test' . time() . '@example.com';
        $user->password = bcrypt('password');
        $user->save();

        $log = new TrainingLog();
        $log->user_id = $user->id;
        $log->session_date = now();
        $log->planned_exercises = [['exercise_id' => 'squat', 'sets' => 4, 'reps' => 8]];
        $log->actual_exercises = [['exercise_id' => 'squat', 'completed_sets' => 4]];
        $log->save();

        $this->assertInstanceOf(User::class, $log->user);
        $this->assertEquals($user->id, $log->user->id);
    }

    /**
     * 测试作用域 - 按用户筛选
     */
    public function test_scope_for_user(): void
    {
        // 直接创建用户
        $user1 = new User();
        $user1->name = 'User 1';
        $user1->email = 'user1_' . time() . '@example.com';
        $user1->password = bcrypt('password');
        $user1->save();

        $user2 = new User();
        $user2->name = 'User 2';
        $user2->email = 'user2_' . time() . '@example.com';
        $user2->password = bcrypt('password');
        $user2->save();

        // 为user1创建3条日志
        for ($i = 0; $i < 3; $i++) {
            $log = new TrainingLog();
            $log->user_id = $user1->id;
            $log->session_date = now()->subDays($i);
            $log->planned_exercises = [['exercise_id' => 'squat', 'sets' => 4, 'reps' => 8]];
            $log->actual_exercises = [['exercise_id' => 'squat', 'completed_sets' => 4]];
            $log->save();
        }

        // 为user2创建2条日志
        for ($i = 0; $i < 2; $i++) {
            $log = new TrainingLog();
            $log->user_id = $user2->id;
            $log->session_date = now()->subDays($i);
            $log->planned_exercises = [['exercise_id' => 'bench', 'sets' => 3, 'reps' => 10]];
            $log->actual_exercises = [['exercise_id' => 'bench', 'completed_sets' => 3]];
            $log->save();
        }

        $user1Logs = TrainingLog::forUser($user1->id)->get();
        $user2Logs = TrainingLog::forUser($user2->id)->get();

        $this->assertCount(3, $user1Logs);
        $this->assertCount(2, $user2Logs);
    }

    /**
     * 测试作用域 - 按日期范围筛选
     */
    public function test_scope_date_range(): void
    {
        // 直接创建用户
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'daterange_' . time() . '@example.com';
        $user->password = bcrypt('password');
        $user->save();

        // 创建不同日期的日志
        $dates = ['2025-12-01', '2025-12-15', '2025-12-25'];
        foreach ($dates as $date) {
            $log = new TrainingLog();
            $log->user_id = $user->id;
            $log->session_date = $date;
            $log->planned_exercises = [['exercise_id' => 'squat', 'sets' => 4, 'reps' => 8]];
            $log->actual_exercises = [['exercise_id' => 'squat', 'completed_sets' => 4]];
            $log->save();
        }

        $logs = TrainingLog::dateRange('2025-12-10', '2025-12-20')->get();

        $this->assertCount(1, $logs);
        $this->assertEquals('2025-12-15', $logs->first()->session_date->format('Y-m-d'));
    }
}
