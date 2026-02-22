<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\Auth\Middleware\JwtAuthenticate;
use App\Http\Middleware\AdminMiddleware;

/**
 * Admin KPI 端点 Feature 测试
 *
 * 测试运营 KPI 聚合指标 API
 *
 * @version v1.0.0
 * @date 2026-02-22
 */
class AdminKpiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([JwtAuthenticate::class, AdminMiddleware::class]);
    }

    protected function createAdmin(): User
    {
        return User::create([
            'name' => 'KPI Admin',
            'email' => 'kpi-admin@test.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
    }

    /**
     * 测试 KPI 概览端点
     */
    public function test_kpi_overview()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/admin/kpi/overview');

        $response->assertStatus(200)
            ->assertJson(['code' => 200])
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => [
                    'dau',
                    'mau',
                    'stickiness_pct',
                    'new_users_today',
                    'total_users',
                    'active_members',
                    'today_queries',
                    'today_revenue',
                ],
            ]);

        // 验证数据类型
        $data = $response->json('data');
        $this->assertIsInt($data['dau']);
        $this->assertIsInt($data['mau']);
        $this->assertIsNumeric($data['stickiness_pct']);
        $this->assertIsInt($data['total_users']);
        $this->assertGreaterThanOrEqual(1, $data['total_users']); // 至少有 admin 自己
    }

    /**
     * 测试用户增长端点
     */
    public function test_kpi_growth()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/admin/kpi/growth');

        $response->assertStatus(200)
            ->assertJson(['code' => 200])
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
            ]);
    }

    /**
     * 测试活跃度端点
     */
    public function test_kpi_activity()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/admin/kpi/activity');

        $response->assertStatus(200)
            ->assertJson(['code' => 200]);
    }

    /**
     * 测试留存率端点
     */
    public function test_kpi_retention()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/admin/kpi/retention');

        $response->assertStatus(200)
            ->assertJson(['code' => 200]);
    }

    /**
     * 测试 Prometheus /metrics 端点
     */
    public function test_prometheus_metrics_endpoint()
    {
        $response = $this->get('/metrics');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

        // 验证包含预期的指标
        $content = $response->getContent();
        $this->assertStringContainsString('http_requests_total', $content);
        $this->assertStringContainsString('php_memory_usage_bytes', $content);
        $this->assertStringContainsString('app_queries_today', $content);
    }
}
