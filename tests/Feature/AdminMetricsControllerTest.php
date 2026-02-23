<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\User\Models\User;
use App\Models\ChatSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use App\Modules\Auth\Middleware\JwtAuthenticate;
use App\Http\Middleware\AdminMiddleware;

/**
 * AdminMetricsController Feature 测试
 *
 * 统一可观测性仪表盘 6 个聚合 API + Redis 缓存测试
 *
 * @version v1.0.0
 * @date 2026-02-24
 */
class AdminMetricsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([JwtAuthenticate::class, AdminMiddleware::class]);
        Cache::flush();
    }

    protected function createAdmin(): User
    {
        return User::create([
            'name' => 'Metrics Admin',
            'email' => 'metrics-admin@test.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
    }

    protected function seedChatSessions(User $user, int $count = 5): void
    {
        for ($i = 0; $i < $count; $i++) {
            ChatSession::create([
                'session_id' => 'test-session-' . $i,
                'user_id' => $user->id,
                'user_query' => '测试问题 ' . $i,
                'llm_response' => '测试回答 ' . $i,
                'model_used' => 'claude-haiku',
                'tools_used' => json_encode(['profile_query', 'exercise_search']),
                'backend_used' => $i % 2 === 0 ? 'anthropic' : 'deepseek',
                'execution_mode' => $i % 3 === 0 ? 'agent' : 'dag',
                'ttfb_ms' => rand(200, 800),
                'duration_ms' => rand(1000, 5000),
                'tokens_per_sec' => rand(20, 60),
                'input_tokens' => rand(100, 500),
                'output_tokens' => rand(200, 1000),
                'estimated_cost' => rand(1, 50) / 10000,
                'credits_consumed' => rand(1, 10),
                'fallback_count' => $i % 4 === 0 ? 1 : 0,
                'error_type' => null,
                'overall_score' => rand(30, 50) / 10,
                'ux_satisfaction' => rand(3, 5),
                'profile_utilization_rate' => rand(40, 95),
                'personalization_grade' => ['S', 'A', 'B', 'C'][$i % 4],
                'fewshot_eligible' => $i % 2 === 0,
                'created_at' => now()->subDays(rand(0, 6)),
            ]);
        }
    }

    // ========== 1. system-overview ==========

    public function test_system_overview_returns_200()
    {
        $admin = $this->createAdmin();
        $this->seedChatSessions($admin);

        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/system-overview?days=7');

        $response->assertStatus(200)
            ->assertJson(['code' => 200])
            ->assertJsonStructure([
                'data' => [
                    'stats' => ['total_requests', 'avg_ttfb_ms', 'avg_duration_ms', 'success_rate', 'active_users', 'total_credits', 'total_tokens'],
                    'trend',
                ],
            ]);
    }

    public function test_system_overview_empty_data()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/system-overview?days=7');

        $response->assertStatus(200)
            ->assertJson(['code' => 200]);

        $data = $response->json('data');
        $this->assertEquals(0, $data['stats']['total_requests']);
    }

    // ========== 2. model-comparison ==========

    public function test_model_comparison_returns_200()
    {
        $admin = $this->createAdmin();
        $this->seedChatSessions($admin);

        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/model-comparison?days=7');

        $response->assertStatus(200)
            ->assertJson(['code' => 200])
            ->assertJsonStructure(['data']);
    }

    public function test_model_comparison_groups_by_backend()
    {
        $admin = $this->createAdmin();
        $this->seedChatSessions($admin, 6);

        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/model-comparison?days=7');

        $data = $response->json('data');
        $backends = array_column($data, 'backend_used');
        $this->assertContains('anthropic', $backends);
        $this->assertContains('deepseek', $backends);
    }

    // ========== 3. mode-comparison ==========

    public function test_mode_comparison_returns_200()
    {
        $admin = $this->createAdmin();
        $this->seedChatSessions($admin);

        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/mode-comparison?days=7');

        $response->assertStatus(200)
            ->assertJson(['code' => 200]);
    }

    public function test_mode_comparison_has_dag_and_agent()
    {
        $admin = $this->createAdmin();
        $this->seedChatSessions($admin, 6);

        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/mode-comparison?days=7');

        $data = $response->json('data');
        $modes = array_column($data, 'execution_mode');
        $this->assertContains('dag', $modes);
        $this->assertContains('agent', $modes);
    }

    // ========== 4. tool-usage ==========

    public function test_tool_usage_returns_200()
    {
        $admin = $this->createAdmin();
        $this->seedChatSessions($admin);

        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/tool-usage?days=7');

        $response->assertStatus(200)
            ->assertJson(['code' => 200])
            ->assertJsonStructure([
                'data' => ['tools', 'combos', 'total_sessions'],
            ]);
    }

    // ========== 5. user-consumption ==========

    public function test_user_consumption_returns_200()
    {
        $admin = $this->createAdmin();
        $this->seedChatSessions($admin);

        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/user-consumption?days=7');

        $response->assertStatus(200)
            ->assertJson(['code' => 200])
            ->assertJsonStructure([
                'data' => ['top_users', 'trend'],
            ]);
    }

    public function test_user_consumption_top_users_has_fields()
    {
        $admin = $this->createAdmin();
        $this->seedChatSessions($admin, 3);

        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/user-consumption?days=7');

        $topUsers = $response->json('data.top_users');
        if (count($topUsers) > 0) {
            $this->assertArrayHasKey('name', $topUsers[0]);
            $this->assertArrayHasKey('total_queries', $topUsers[0]);
            $this->assertArrayHasKey('total_credits', $topUsers[0]);
        }
    }

    // ========== 6. quality-trend ==========

    public function test_quality_trend_returns_200()
    {
        $admin = $this->createAdmin();
        $this->seedChatSessions($admin);

        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/quality-trend?days=7');

        $response->assertStatus(200)
            ->assertJson(['code' => 200])
            ->assertJsonStructure([
                'data' => ['trend', 'grade_distribution'],
            ]);
    }

    // ========== 7. days 参数验证 ==========

    public function test_days_param_defaults_to_7()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/system-overview');

        $response->assertStatus(200);
    }

    public function test_days_param_accepts_30()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/system-overview?days=30');

        $response->assertStatus(200);
    }

    // ========== 8. Redis 缓存 ==========

    public function test_cache_hit_returns_same_data()
    {
        $admin = $this->createAdmin();
        $this->seedChatSessions($admin);

        // 第一次请求（缓存 miss）
        $response1 = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/system-overview?days=7');
        $data1 = $response1->json('data');

        // 第二次请求（缓存 hit）
        $response2 = $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/system-overview?days=7');
        $data2 = $response2->json('data');

        $this->assertEquals($data1, $data2);
    }

    public function test_cache_clear_works()
    {
        $admin = $this->createAdmin();
        $this->seedChatSessions($admin);

        // 触发缓存
        $this->actingAs($admin)->getJson('/api/admin/metrics/dashboard/system-overview?days=7');

        // 验证缓存存在
        $this->assertTrue(Cache::has('admin:metrics:system-overview:7'));

        // 清除缓存
        \App\Http\Controllers\Api\Admin\MetricsController::clearDashboardCache();

        // 验证缓存已清除
        $this->assertFalse(Cache::has('admin:metrics:system-overview:7'));
    }
}
