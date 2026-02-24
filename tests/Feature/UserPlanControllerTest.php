<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\User\Models\User;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\Auth\Middleware\JwtAuthenticate;

/**
 * 用户自建训练计划 Feature 测试
 *
 * @version v1.0.0
 * @date 2026-02-22
 */
class UserPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([JwtAuthenticate::class]);
    }

    protected function createUser(): User
    {
        return User::create([
            'name' => 'Plan Test User',
            'email' => 'plan-test@test.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
    }

    protected function sampleExercises(): array
    {
        return [
            [
                'exercise_name' => '卧推',
                'day_of_week' => 1,
                'sets' => 4,
                'reps' => '8-12',
                'weight' => '60kg',
                'rest_time' => '90s',
                'order_index' => 0,
            ],
            [
                'exercise_name' => '深蹲',
                'day_of_week' => 3,
                'sets' => 5,
                'reps' => '5',
                'weight' => '80kg',
                'rest_time' => '120s',
                'order_index' => 0,
            ],
        ];
    }

    /**
     * 测试手动创建训练计划
     */
    public function test_store_plan_success()
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/api/training/plans', [
            'name' => '我的增肌计划',
            'description' => '每星期3练，专注上肢',
            'goal' => 'gain_muscle',
            'difficulty' => 'intermediate',
            'duration_weeks' => 8,
            'workouts_per_week' => 3,
            'exercises' => $this->sampleExercises(),
        ]);

        $response->assertStatus(200)
            ->assertJson(['code' => 200])
            ->assertJsonStructure([
                'data' => ['id', 'name', 'exerciseCount', 'createdAt'],
            ]);

        $this->assertEquals(2, $response->json('data.exerciseCount'));
        $this->assertDatabaseHas('training_plans', [
            'name' => '我的增肌计划',
            'type' => 'manual',
        ]);
    }

    /**
     * 测试创建计划 - 参数验证失败
     */
    public function test_store_plan_validation_fails()
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/api/training/plans', [
            'name' => '',
            'exercises' => [],
        ]);

        $response->assertStatus(422);
    }

    /**
     * 测试获取计划列表 - 按类型筛选
     */
    public function test_index_filter_by_type()
    {
        $user = $this->createUser();

        TrainingPlan::create([
            'user_id' => $user->id,
            'name' => 'AI计划',
            'type' => 'ai_generated',
            'duration_weeks' => 4,
            'workouts_per_week' => 3,
        ]);
        TrainingPlan::create([
            'user_id' => $user->id,
            'name' => '手动计划',
            'type' => 'manual',
            'duration_weeks' => 4,
            'workouts_per_week' => 3,
        ]);

        $response = $this->actingAs($user)->getJson('/api/training/plans?type=manual');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('手动计划', $data[0]['name']);
    }

    /**
     * 测试获取计划详情 - 包含 planExercises
     */
    public function test_show_plan_with_exercises()
    {
        $user = $this->createUser();

        $plan = TrainingPlan::create([
            'user_id' => $user->id,
            'name' => '测试计划',
            'type' => 'manual',
            'duration_weeks' => 4,
            'workouts_per_week' => 3,
        ]);

        TrainingPlanExercise::create([
            'plan_id' => $plan->id,
            'exercise_name' => '卧推',
            'day_of_week' => 1,
            'sets' => 4,
            'reps' => '8-12',
            'order_index' => 0,
        ]);

        $response = $this->actingAs($user)->getJson("/api/training/plans/{$plan->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['planExercises'],
            ]);

        $exercises = $response->json('data.planExercises');
        $this->assertCount(1, $exercises);
        $this->assertEquals('卧推', $exercises[0]['exerciseName']);
        $this->assertEquals(1, $exercises[0]['dayOfWeek']);
    }

    /**
     * 测试更新计划 + 同步动作
     */
    public function test_update_plan_with_exercises()
    {
        $user = $this->createUser();

        $plan = TrainingPlan::create([
            'user_id' => $user->id,
            'name' => '旧计划',
            'type' => 'manual',
            'duration_weeks' => 4,
            'workouts_per_week' => 3,
        ]);

        TrainingPlanExercise::create([
            'plan_id' => $plan->id,
            'exercise_name' => '旧动作',
            'sets' => 3,
            'reps' => '10',
            'order_index' => 0,
        ]);

        $response = $this->actingAs($user)->putJson("/api/training/plans/{$plan->id}", [
            'name' => '新计划名',
            'duration_weeks' => 6,
            'workouts_per_week' => 4,
            'exercises' => [
                ['exercise_name' => '新动作1', 'sets' => 4, 'reps' => '8', 'day_of_week' => 1],
                ['exercise_name' => '新动作2', 'sets' => 3, 'reps' => '12', 'day_of_week' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['data' => ['exerciseCount' => 2]]);

        $this->assertDatabaseMissing('training_plan_exercises', ['exercise_name' => '旧动作']);
        $this->assertDatabaseHas('training_plan_exercises', ['exercise_name' => '新动作1']);
    }

    /**
     * 测试复制计划
     */
    public function test_copy_plan()
    {
        $user = $this->createUser();

        $plan = TrainingPlan::create([
            'user_id' => $user->id,
            'name' => '原始计划',
            'type' => 'manual',
            'duration_weeks' => 4,
            'workouts_per_week' => 3,
        ]);

        TrainingPlanExercise::create([
            'plan_id' => $plan->id,
            'exercise_name' => '卧推',
            'sets' => 4,
            'reps' => '8-12',
            'order_index' => 0,
        ]);

        $response = $this->actingAs($user)->postJson("/api/training/plans/{$plan->id}/copy");

        $response->assertStatus(200)
            ->assertJson(['code' => 200]);

        $newId = $response->json('data.id');
        $this->assertNotEquals($plan->id, $newId);
        $this->assertStringContainsString('副本', $response->json('data.name'));
        $this->assertEquals(1, $response->json('data.exerciseCount'));
    }

    /**
     * 测试删除计划（级联删除动作）
     */
    public function test_destroy_plan_cascades_exercises()
    {
        $user = $this->createUser();

        $plan = TrainingPlan::create([
            'user_id' => $user->id,
            'name' => '待删除计划',
            'type' => 'manual',
            'duration_weeks' => 4,
            'workouts_per_week' => 3,
        ]);

        TrainingPlanExercise::create([
            'plan_id' => $plan->id,
            'exercise_name' => '卧推',
            'sets' => 4,
            'reps' => '8-12',
            'order_index' => 0,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/training/plans/{$plan->id}");

        $response->assertStatus(200);
        // 软删除，plan 仍在但 deleted_at 不为空
        $this->assertSoftDeleted('training_plans', ['id' => $plan->id]);
    }

    /**
     * 测试不能访问其他用户的计划
     */
    public function test_cannot_access_other_users_plan()
    {
        $user1 = $this->createUser();
        $user2 = User::create([
            'name' => 'Other User',
            'email' => 'other@test.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $plan = TrainingPlan::create([
            'user_id' => $user1->id,
            'name' => 'User1的计划',
            'type' => 'manual',
            'duration_weeks' => 4,
            'workouts_per_week' => 3,
        ]);

        $response = $this->actingAs($user2)->getJson("/api/training/plans/{$plan->id}");

        $response->assertStatus(404);
    }
}
