<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * 训练记录功能测试
 * 
 * @version 1.0.0
 * @date 2025-12-19
 */
class TrainingRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private UserProfile $userProfile;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试用户
        $this->user = User::factory()->create();

        // 创建用户档案
        $this->userProfile = UserProfile::create([
            'user_id' => $this->user->id,
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
    }

    /**
     * 测试记录训练数据
     */
    public function test_record_training_data()
    {
        $response = $this->postJson('/api/training/record', [
            'user_id' => $this->user->id,
            'exercise_name' => 'squat',
            'weight' => 100,
            'reps' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '训练数据记录成功',
            ]);

        // 验证数据
        $data = $response->json('data');
        $this->assertEquals('squat', $data['exercise_name']);
        $this->assertEquals(116.7, $data['progress']['current_1rm']);
        $this->assertNotNull($data['progress']['strength_level']);
    }

    /**
     * 测试批量记录训练数据
     */
    public function test_record_training_batch()
    {
        $response = $this->postJson('/api/training/record-batch', [
            'user_id' => $this->user->id,
            'records' => [
                [
                    'exercise_name' => 'squat',
                    'weight' => 100,
                    'reps' => 5,
                ],
                [
                    'exercise_name' => 'bench_press',
                    'weight' => 80,
                    'reps' => 5,
                ],
                [
                    'exercise_name' => 'deadlift',
                    'weight' => 120,
                    'reps' => 5,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '批量记录训练数据成功',
            ]);

        // 验证数据
        $data = $response->json('data');
        $this->assertCount(3, $data['records']);
        $this->assertNotNull($data['overall_strength_level']);
    }

    /**
     * 测试获取力量进步曲线（所有动作）
     */
    public function test_get_strength_progress_all()
    {
        // 先记录一些数据
        $this->userProfile->recordStrengthProgress('squat', 100, 5);
        $this->userProfile->recordStrengthProgress('bench_press', 80, 5);

        $response = $this->getJson("/api/training/progress/{$this->user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 验证数据
        $data = $response->json('data');
        $this->assertArrayHasKey('strength_progress', $data);
        $this->assertArrayHasKey('squat', $data['strength_progress']);
        $this->assertArrayHasKey('bench_press', $data['strength_progress']);
        $this->assertArrayHasKey('current_1rms', $data);
        $this->assertArrayHasKey('overall_strength_level', $data);
    }

    /**
     * 测试获取力量进步曲线（特定动作）
     */
    public function test_get_strength_progress_specific()
    {
        // 先记录一些数据
        $this->userProfile->recordStrengthProgress('squat', 100, 5);

        $response = $this->getJson("/api/training/progress/{$this->user->id}/squat");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 验证数据
        $data = $response->json('data');
        $this->assertEquals('squat', $data['exercise_name']);
        $this->assertArrayHasKey('progress', $data);
        $this->assertArrayHasKey('history', $data['progress']);
        $this->assertArrayHasKey('current_1rm', $data['progress']);
    }

    /**
     * 测试删除训练记录
     */
    public function test_delete_training_record()
    {
        // 先记录一些数据
        $this->userProfile->recordStrengthProgress('squat', 80, 5);
        $this->userProfile->recordStrengthProgress('squat', 100, 5);

        // 删除第一条记录
        $response = $this->deleteJson("/api/training/record/{$this->user->id}/squat/0");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '训练记录删除成功',
            ]);

        // 验证数据
        $data = $response->json('data');
        $this->assertCount(1, $data['strength_progress']['squat']['history']);
    }

    /**
     * 测试1RM估算
     */
    public function test_1rm_estimation()
    {
        // 测试不同重量和次数的1RM估算
        $testCases = [
            ['weight' => 100, 'reps' => 1, 'expected' => 100],
            ['weight' => 100, 'reps' => 5, 'expected' => 116.7],
            ['weight' => 80, 'reps' => 10, 'expected' => 106.7],
        ];

        foreach ($testCases as $case) {
            $response = $this->postJson('/api/training/record', [
                'user_id' => $this->user->id,
                'exercise_name' => 'test_exercise',
                'weight' => $case['weight'],
                'reps' => $case['reps'],
            ]);

            $data = $response->json('data');
            $this->assertEquals($case['expected'], $data['progress']['current_1rm']);
        }
    }

    /**
     * 测试力量水平评估
     */
    public function test_strength_level_assessment()
    {
        // 测试不同1RM对应的力量水平
        // 假设用户体重70kg

        // 深蹲35kg (0.5×体重) → beginner
        $response = $this->postJson('/api/training/record', [
            'user_id' => $this->user->id,
            'exercise_name' => 'squat',
            'weight' => 30,
            'reps' => 1,
        ]);
        $data = $response->json('data');
        $this->assertEquals('beginner', $data['progress']['strength_level']);

        // 深蹲105kg (1.5×体重) → intermediate
        $response = $this->postJson('/api/training/record', [
            'user_id' => $this->user->id,
            'exercise_name' => 'squat',
            'weight' => 90,
            'reps' => 5,  // 估算1RM = 105kg
        ]);
        $data = $response->json('data');
        $this->assertEquals('intermediate', $data['progress']['strength_level']);
    }

    /**
     * 测试数据验证
     */
    public function test_validation()
    {
        // 测试缺少必需字段
        $response = $this->postJson('/api/training/record', [
            'user_id' => $this->user->id,
            // 缺少 exercise_name, weight, reps
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => '数据验证失败',
            ]);

        // 测试无效的重量
        $response = $this->postJson('/api/training/record', [
            'user_id' => $this->user->id,
            'exercise_name' => 'squat',
            'weight' => -10,  // 负数
            'reps' => 5,
        ]);

        $response->assertStatus(422);

        // 测试无效的次数
        $response = $this->postJson('/api/training/record', [
            'user_id' => $this->user->id,
            'exercise_name' => 'squat',
            'weight' => 100,
            'reps' => 0,  // 小于1
        ]);

        $response->assertStatus(422);
    }
}
