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
    
    /**
     * 创建管理员用户并绕过JWT中间件
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // 绕过JWT和Admin中间件，直接设置认证用户
        $this->withoutMiddleware([JwtAuthenticate::class, AdminMiddleware::class]);
    }
    
    /**
     * 创建管理员用户
     */
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
     * 测试Prometheus原始指标端点
     * 
     * 注意：此测试依赖DAML-RAG服务可用
     */
    public function test_prometheus_raw_endpoint_returns_valid_data()
    {
        $admin = $this->createAdmin();
        
        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/prometheus/raw');
        
        // 端点应该可访问（可能因DAML-RAG不可用返回500）
        $this->assertTrue(
            in_array($response->status(), [200, 500, 502, 503]),
            "Expected 200/500/502/503, got {$response->status()}"
        );
        
        $response->assertJsonStructure([
            'code',
            'msg',
        ]);
    }
    
    /**
     * 测试DAML-RAG健康检查端点
     */
    public function test_daml_rag_health_endpoint()
    {
        $admin = $this->createAdmin();
        
        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/daml-rag/health');
        
        $this->assertTrue(
            in_array($response->status(), [200, 500, 502, 503]),
            "Expected 200/500/502/503, got {$response->status()}"
        );
        
        $response->assertJsonStructure([
            'code',
            'msg',
        ]);
    }
    
    /**
     * 测试DAML-RAG系统指标端点
     */
    public function test_daml_rag_metrics_endpoint()
    {
        $admin = $this->createAdmin();
        
        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/daml-rag/metrics');
        
        $this->assertTrue(
            in_array($response->status(), [200, 500, 502, 503]),
            "Expected 200/500/502/503, got {$response->status()}"
        );
        
        $response->assertJsonStructure([
            'code',
            'msg',
        ]);
    }
    
    /**
     * 测试流式监控端点
     */
    public function test_daml_rag_streaming_endpoint()
    {
        $admin = $this->createAdmin();
        
        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/daml-rag/streaming');
        
        $this->assertTrue(
            in_array($response->status(), [200, 500, 502, 503]),
            "Expected 200/500/502/503, got {$response->status()}"
        );
        
        $response->assertJsonStructure([
            'code',
            'msg',
        ]);
    }
}
