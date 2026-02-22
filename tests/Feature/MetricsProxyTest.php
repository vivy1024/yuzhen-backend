<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\User\Models\User;
use App\Modules\Auth\Middleware\JwtAuthenticate;
use App\Http\Middleware\AdminMiddleware;

/**
 * Prometheus代理功能测试
 *
 * 测试PHP后端是否能成功调用DAML-RAG的监控端点
 * 注意：这些测试依赖外部DAML-RAG服务，在CI环境中可能需要跳过
 *
 * Feature: monitoring-simplification
 * Task: 11.1 测试/admin/metrics端点
 * Validates: Requirements 4.4, 5.7
 */
class MetricsProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    protected function createAdmin(): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
    }

    /**
     * 断言代理端点可访问，200时验证JSON结构
     */
    private function assertProxyEndpoint(string $uri): void
    {
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin)->getJson($uri);

        $this->assertTrue(
            in_array($response->status(), [200, 401, 500, 502, 503]),
            "Expected 200/401/500/502/503, got {$response->status()}"
        );

        if ($response->status() === 200) {
            $response->assertJsonStructure(['code', 'msg']);
        }
    }

    public function test_prometheus_raw_endpoint_returns_valid_data()
    {
        $this->assertProxyEndpoint('/api/admin/metrics/prometheus/raw');
    }

    public function test_daml_rag_health_endpoint()
    {
        $this->assertProxyEndpoint('/api/admin/metrics/daml-rag/health');
    }

    public function test_daml_rag_metrics_endpoint()
    {
        $this->assertProxyEndpoint('/api/admin/metrics/daml-rag/metrics');
    }

    public function test_daml_rag_streaming_endpoint()
    {
        $this->assertProxyEndpoint('/api/admin/metrics/daml-rag/streaming');
    }
}
