<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserProfile;
use App\Modules\Auth\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * 训练记录功能测试
 * 
 * 测试训练数据记录、力量进步追踪等API
 * API使用统一响应格式：{code, msg, data}
 * 
 * @version 2.0.0
 * @date 2025-12-19
 */
class TrainingRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private UserProfile $userProfile;
    private string $token;

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

        // JWT Token
        $jwtService = app(JwtService::class);
        $this->token = $jwtService->generateToken($this->user);
    }

    private function authPostJson(string $uri, array $data = [])
    {
        return $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson($uri, $data);
    }

    private function authGetJson(string $uri)
    {
        return $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson($uri);
    }

    private function authDeleteJson(string $uri)
    {
        return $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->deleteJson($uri);
    }

    /**
     * 测试记录训练数据
     */
    public function test_record_training_data()
    {
        $response = $this->authPostJson('/api/training/record', [
            'exercise_name' => 'squat',
            'weight' => 100,
            'reps' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'code' => 200,
                'msg' => '训练数据记录成功',
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
        $response = $this->authPostJson('/api/training/record-batch', [
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
                'code' => 200,
                'msg' => '批量记录训练数据成功',
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

        $response = $this->authGetJson("/api/training/progress");

        $response->assertStatus(200)
            ->assertJson([
                'code' => 200,
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

        $response = $this->authGetJson("/api/training/progress/squat");

        $response->assertStatus(200)
            ->assertJson([
                'code' => 200,
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
        $response = $this->authDeleteJson("/api/training/record/squat/0");

        $response->assertStatus(200)
            ->assertJson([
                'code' => 200,
                'msg' => '训练记录删除成功',
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
            $response = $this->authPostJson('/api/training/record', [
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
        // 深蹲30kg (0.43×体重70kg) → 应该是较低水平
        $response = $this->authPostJson('/api/training/record', [
            'exercise_name' => 'squat',
            'weight' => 30,
            'reps' => 1,
        ]);
        $data = $response->json('data');
        $this->assertNotNull($data['progress']['strength_level']);
        // 30kg/70kg = 0.43, 较低水平
        $this->assertContains($data['progress']['strength_level'], ['untrained', 'beginner']);

        // 深蹲105kg (1.5×体重) → intermediate
        $response = $this->authPostJson('/api/training/record', [
            'exercise_name' => 'squat',
            'weight' => 90,
            'reps' => 5,  // 估算1RM ≈ 105kg
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
        $response = $this->authPostJson('/api/training/record', [
            // 缺少 exercise_name, weight, reps
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 422,
                'msg' => '数据验证失败',
            ]);

        // 测试无效的重量
        $response = $this->authPostJson('/api/training/record', [
            'exercise_name' => 'squat',
            'weight' => -10,  // 负数
            'reps' => 5,
        ]);

        $response->assertStatus(422);

        // 测试无效的次数
        $response = $this->authPostJson('/api/training/record', [
            'exercise_name' => 'squat',
            'weight' => 100,
            'reps' => 0,  // 小于1
        ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_returns_401()
    {
        $response = $this->postJson('/api/training/record', [
            'exercise_name' => 'squat', 'weight' => 100, 'reps' => 5,
        ]);
        $response->assertStatus(401);
    }
}
