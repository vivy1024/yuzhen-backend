<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\PersonalBest;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * PersonalBest 单元测试
 * 
 * 测试个人最佳记录模型的核心功能：
 * - 1RM计算 (Epley公式)
 * - 个人最佳记录更新逻辑 (Requirements: 6.4)
 * - 记录创建和获取
 * 
 * @version 1.0.0
 * @date 2025-12-26
 */
class PersonalBestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试1RM计算 - Epley公式
     * 
     * Epley公式: 1RM = weight × (1 + reps/30)
     */
    public function test_calculate_1rm_epley_formula(): void
    {
        // 100kg x 10次 = 100 × (1 + 10/30) = 100 × 1.333 = 133.33
        $result = PersonalBest::calculate1RM(100, 10);
        $this->assertEquals(133.33, $result);

        // 80kg x 8次 = 80 × (1 + 8/30) = 80 × 1.267 = 101.33
        $result = PersonalBest::calculate1RM(80, 8);
        $this->assertEquals(101.33, $result);

        // 60kg x 12次 = 60 × (1 + 12/30) = 60 × 1.4 = 84.0
        $result = PersonalBest::calculate1RM(60, 12);
        $this->assertEquals(84.0, $result);
    }

    /**
     * 测试1RM计算 - 单次最大重量
     */
    public function test_calculate_1rm_single_rep(): void
    {
        // 1次时，1RM就是重量本身
        $result = PersonalBest::calculate1RM(150, 1);
        $this->assertEquals(150, $result);
    }

    /**
     * 测试1RM计算 - 边界情况
     */
    public function test_calculate_1rm_edge_cases(): void
    {
        // 0重量
        $result = PersonalBest::calculate1RM(0, 10);
        $this->assertEquals(0.0, $result);

        // 0次数
        $result = PersonalBest::calculate1RM(100, 0);
        $this->assertEquals(0.0, $result);

        // 负数重量
        $result = PersonalBest::calculate1RM(-50, 10);
        $this->assertEquals(0.0, $result);

        // 负数次数
        $result = PersonalBest::calculate1RM(100, -5);
        $this->assertEquals(0.0, $result);
    }

    /**
     * 测试更新个人最佳记录 - 打破记录
     * 
     * Requirements: 6.4 - 如果重量或次数超过当前PersonalBest，系统应该更新记录
     */
    public function test_update_best_breaks_record(): void
    {
        $user = $this->createTestUser();

        // 创建初始记录
        $pb = PersonalBest::create([
            'user_id' => $user->id,
            'exercise_id' => 'squat',
            'exercise_name' => '深蹲',
            'best_weight' => 100,
            'best_reps' => 5,
            'estimated_1rm' => PersonalBest::calculate1RM(100, 5), // 116.67
            'achieved_date' => now()->subDays(7),
            'usage_count' => 1,
        ]);

        // 尝试打破记录：120kg x 5次 = 140.0 > 116.67
        $isNewRecord = $pb->updateBest(120, 5);

        $this->assertTrue($isNewRecord);
        $this->assertEquals(120, $pb->best_weight);
        $this->assertEquals(5, $pb->best_reps);
        $this->assertEquals(140.0, $pb->estimated_1rm);
        $this->assertEquals(2, $pb->usage_count);
    }

    /**
     * 测试更新个人最佳记录 - 未打破记录
     */
    public function test_update_best_no_new_record(): void
    {
        $user = $this->createTestUser();

        // 创建初始记录
        $pb = PersonalBest::create([
            'user_id' => $user->id,
            'exercise_id' => 'bench',
            'exercise_name' => '卧推',
            'best_weight' => 100,
            'best_reps' => 8,
            'estimated_1rm' => PersonalBest::calculate1RM(100, 8), // 126.67
            'achieved_date' => now()->subDays(7),
            'usage_count' => 5,
        ]);

        $originalBestWeight = $pb->best_weight;
        $originalBestReps = $pb->best_reps;
        $original1RM = $pb->estimated_1rm;

        // 尝试更新：80kg x 10次 = 106.67 < 126.67
        $isNewRecord = $pb->updateBest(80, 10);

        $this->assertFalse($isNewRecord);
        // 最佳记录应保持不变
        $this->assertEquals($originalBestWeight, $pb->best_weight);
        $this->assertEquals($originalBestReps, $pb->best_reps);
        $this->assertEquals($original1RM, $pb->estimated_1rm);
        // 但使用记录应更新
        $this->assertEquals(80, $pb->last_used_weight);
        $this->assertEquals(6, $pb->usage_count);
    }

    /**
     * 测试更新个人最佳记录 - 相同1RM不算打破记录
     */
    public function test_update_best_same_1rm_not_new_record(): void
    {
        $user = $this->createTestUser();

        // 创建初始记录：100kg x 6次 = 120.0
        $pb = PersonalBest::create([
            'user_id' => $user->id,
            'exercise_id' => 'deadlift',
            'exercise_name' => '硬拉',
            'best_weight' => 100,
            'best_reps' => 6,
            'estimated_1rm' => 120.0,
            'achieved_date' => now()->subDays(7),
            'usage_count' => 1,
        ]);

        // 尝试更新：相同的1RM
        $isNewRecord = $pb->updateBest(100, 6);

        $this->assertFalse($isNewRecord);
    }

    /**
     * 测试获取或创建记录 - 新记录
     */
    public function test_get_or_create_new_record(): void
    {
        $user = $this->createTestUser();

        $pb = PersonalBest::getOrCreate($user->id, 'overhead_press', '过头推举');

        $this->assertNotNull($pb);
        $this->assertEquals($user->id, $pb->user_id);
        $this->assertEquals('overhead_press', $pb->exercise_id);
        $this->assertEquals('过头推举', $pb->exercise_name);
        $this->assertEquals(0, $pb->usage_count);
    }

    /**
     * 测试获取或创建记录 - 已存在记录
     */
    public function test_get_or_create_existing_record(): void
    {
        $user = $this->createTestUser();

        // 先创建记录
        $original = PersonalBest::create([
            'user_id' => $user->id,
            'exercise_id' => 'row',
            'exercise_name' => '划船',
            'best_weight' => 80,
            'best_reps' => 10,
            'estimated_1rm' => 106.67,
            'usage_count' => 5,
        ]);

        // 再次获取
        $pb = PersonalBest::getOrCreate($user->id, 'row', '划船');

        $this->assertEquals($original->id, $pb->id);
        $this->assertEquals(80, $pb->best_weight);
        $this->assertEquals(5, $pb->usage_count);
    }

    /**
     * 测试作用域 - 按用户筛选
     */
    public function test_scope_for_user(): void
    {
        $user1 = $this->createTestUser('user1');
        $user2 = $this->createTestUser('user2');

        // 为user1创建3条记录
        PersonalBest::create(['user_id' => $user1->id, 'exercise_id' => 'squat', 'usage_count' => 0]);
        PersonalBest::create(['user_id' => $user1->id, 'exercise_id' => 'bench', 'usage_count' => 0]);
        PersonalBest::create(['user_id' => $user1->id, 'exercise_id' => 'deadlift', 'usage_count' => 0]);

        // 为user2创建2条记录
        PersonalBest::create(['user_id' => $user2->id, 'exercise_id' => 'squat', 'usage_count' => 0]);
        PersonalBest::create(['user_id' => $user2->id, 'exercise_id' => 'bench', 'usage_count' => 0]);

        $user1Records = PersonalBest::forUser($user1->id)->get();
        $user2Records = PersonalBest::forUser($user2->id)->get();

        $this->assertCount(3, $user1Records);
        $this->assertCount(2, $user2Records);
    }

    /**
     * 测试作用域 - 按动作筛选
     */
    public function test_scope_for_exercise(): void
    {
        $user = $this->createTestUser();

        PersonalBest::create(['user_id' => $user->id, 'exercise_id' => 'squat', 'usage_count' => 0]);
        PersonalBest::create(['user_id' => $user->id, 'exercise_id' => 'bench', 'usage_count' => 0]);

        $squatRecord = PersonalBest::forUser($user->id)->forExercise('squat')->first();
        $benchRecord = PersonalBest::forUser($user->id)->forExercise('bench')->first();
        $deadliftRecord = PersonalBest::forUser($user->id)->forExercise('deadlift')->first();

        $this->assertNotNull($squatRecord);
        $this->assertEquals('squat', $squatRecord->exercise_id);
        $this->assertNotNull($benchRecord);
        $this->assertEquals('bench', $benchRecord->exercise_id);
        $this->assertNull($deadliftRecord);
    }

    /**
     * 测试用户关联
     */
    public function test_user_relationship(): void
    {
        $user = $this->createTestUser();

        $pb = PersonalBest::create([
            'user_id' => $user->id,
            'exercise_id' => 'squat',
            'exercise_name' => '深蹲',
            'usage_count' => 0,
        ]);

        $this->assertInstanceOf(User::class, $pb->user);
        $this->assertEquals($user->id, $pb->user->id);
    }

    /**
     * 测试last_used字段更新
     */
    public function test_last_used_fields_update(): void
    {
        $user = $this->createTestUser();

        $pb = PersonalBest::create([
            'user_id' => $user->id,
            'exercise_id' => 'squat',
            'exercise_name' => '深蹲',
            'usage_count' => 0,
        ]);

        // 更新记录
        $pb->updateBest(100, 8);

        $this->assertEquals(100, $pb->last_used_weight);
        $this->assertEquals(now()->toDateString(), $pb->last_used_date->toDateString());
        $this->assertEquals(1, $pb->usage_count);

        // 再次更新
        $pb->updateBest(105, 6);

        $this->assertEquals(105, $pb->last_used_weight);
        $this->assertEquals(2, $pb->usage_count);
    }

    /**
     * 测试achieved_date只在打破记录时更新
     */
    public function test_achieved_date_only_updates_on_new_record(): void
    {
        $user = $this->createTestUser();

        $originalDate = now()->subDays(30);

        $pb = PersonalBest::create([
            'user_id' => $user->id,
            'exercise_id' => 'squat',
            'exercise_name' => '深蹲',
            'best_weight' => 100,
            'best_reps' => 5,
            'estimated_1rm' => 116.67,
            'achieved_date' => $originalDate,
            'usage_count' => 1,
        ]);

        // 未打破记录的更新
        $pb->updateBest(80, 8);
        $this->assertEquals($originalDate->toDateString(), $pb->achieved_date->toDateString());

        // 打破记录的更新
        $pb->updateBest(120, 5);
        $this->assertEquals(now()->toDateString(), $pb->achieved_date->toDateString());
    }

    /**
     * 创建测试用户的辅助方法
     */
    private function createTestUser(string $suffix = ''): User
    {
        $user = new User();
        $user->name = 'Test User ' . $suffix;
        $user->email = 'test_' . time() . '_' . $suffix . '@example.com';
        $user->password = bcrypt('password');
        $user->save();
        return $user;
    }
}
