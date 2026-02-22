<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\User\Models\User;
use App\Models\CreditTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Infrastructure\Http\Middleware\InternalApiAuth;

/**
 * 积分服务 Feature 测试
 *
 * 测试积分扣减、幂等性、余额不足等场景
 *
 * @version v1.0.0
 * @date 2026-02-22
 */
class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([InternalApiAuth::class]);
    }

    protected function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'credit-test@test.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'membership_tier' => 'warmheart',
        ], $overrides));
    }

    /**
     * 测试正常积分扣减
     */
    public function test_record_consumption_success()
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/internal/credits/record', [
            'user_id' => $user->id,
            'tokens' => 1000,
            'mode' => 'dag',
            'template_name' => 'exercise_recommendation',
            'conversation_id' => 'conv_test_001',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => [
                    'transaction_id',
                    'credits_consumed',
                    'remaining_credits',
                ],
            ])
            ->assertJson(['code' => 200]);
    }

    /**
     * 测试幂等性 - 相同 conversation_id 不重复扣减
     */
    public function test_idempotent_consumption()
    {
        $user = $this->createUser();
        $convId = 'conv_idempotent_001';

        // 第一次请求
        $response1 = $this->postJson('/api/internal/credits/record', [
            'user_id' => $user->id,
            'tokens' => 500,
            'mode' => 'dag',
            'conversation_id' => $convId,
        ]);
        $response1->assertStatus(200);
        $firstCredits = $response1->json('data.remaining_credits');

        // 第二次相同 conversation_id
        $response2 = $this->postJson('/api/internal/credits/record', [
            'user_id' => $user->id,
            'tokens' => 500,
            'mode' => 'dag',
            'conversation_id' => $convId,
        ]);
        $response2->assertStatus(200)
            ->assertJson(['data' => ['idempotent' => true]]);

        // 余额不应再次扣减
        $this->assertEquals($firstCredits, $response2->json('data.remaining_credits'));
    }

    /**
     * 测试参数验证 - 缺少必填字段
     */
    public function test_validation_missing_required_fields()
    {
        $response = $this->postJson('/api/internal/credits/record', []);

        $response->assertStatus(400)
            ->assertJson(['code' => 400]);
    }

    /**
     * 测试参数验证 - 无效 mode
     */
    public function test_validation_invalid_mode()
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/internal/credits/record', [
            'user_id' => $user->id,
            'tokens' => 100,
            'mode' => 'invalid_mode',
        ]);

        $response->assertStatus(400);
    }

    /**
     * 测试参数验证 - user_id 为负数
     */
    public function test_validation_negative_user_id()
    {
        $response = $this->postJson('/api/internal/credits/record', [
            'user_id' => -1,
            'tokens' => 100,
            'mode' => 'dag',
        ]);

        $response->assertStatus(400);
    }
}
