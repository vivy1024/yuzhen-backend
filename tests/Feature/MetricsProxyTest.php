<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

/**
 * Prometheus代理功能测试
 * 
 * 测试PHP后端是否能成功调用DAML-RAG的Prometheus端点
 * 
 * Feature: monitoring-simplification
 * Task: 11.1 测试/admin/metrics/prometheus/raw端点
 * Validates: Requirements 4.4, 5.7
 */
class MetricsProxyTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * 创建管理员用户
     */
    protected function createAdmin()
    {
        return User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com'
        ]);
    }
    
    /**
     * 测试Prometheus原始指标端点
     * 
     * @test
     */
    public function test_prometheus_raw_endpoint_returns_valid_data()
    {
        $admin = $this->createAdmin();
        
        // 调用PHP后端的Prometheus代理端点
        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/prometheus/raw');
        
        // 验证HTTP状态码
        $response->assertStatus(200);
        
        // 验证返回JSON格式
        $response->assertJsonStructure([
            'code',
            'msg',
            'data'
        ]);
        
        // 验证返回成功
        $data = $response->json();
        $this->assertEquals(200, $data['code']);
        $this->assertEquals('success', $data['msg']);
        
        // 验证data字段是数组
        $this->assertIsArray($data['data']);
        
        // 验证至少有一些指标
        $this->assertNotEmpty($data['data'], 'Prometheus metrics should not be empty');
        
        // 验证指标格式
        $firstMetric = $data['data'][0];
        $this->assertArrayHasKey('name', $firstMetric);
        $this->assertArrayHasKey('labels', $firstMetric);
        $this->assertArrayHasKey('value', $firstMetric);
        
        // 验证指标名称不为空
        $this->assertNotEmpty($firstMetric['name']);
        
        // 验证labels是数组
        $this->assertIsArray($firstMetric['labels']);
        
        // 验证value是数值
        $this->assertTrue(
            is_numeric($firstMetric['value']) || is_string($firstMetric['value']),
            'Metric value should be numeric or string'
        );
        
        echo "\n✅ Prometheus代理测试通过\n";
        echo "   - 返回状态码: 200\n";
        echo "   - 指标数量: " . count($data['data']) . "\n";
        echo "   - 第一个指标: {$firstMetric['name']}\n";
    }
    
    /**
     * 测试DAML-RAG健康检查端点
     * 
     * @test
     */
    public function test_daml_rag_health_endpoint()
    {
        $admin = $this->createAdmin();
        
        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/daml-rag/health');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'code',
            'msg',
            'data'
        ]);
        
        $data = $response->json();
        $this->assertEquals(200, $data['code']);
        
        echo "\n✅ DAML-RAG健康检查测试通过\n";
    }
    
    /**
     * 测试DAML-RAG系统指标端点
     * 
     * @test
     */
    public function test_daml_rag_metrics_endpoint()
    {
        $admin = $this->createAdmin();
        
        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/daml-rag/metrics');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'code',
            'msg',
            'data'
        ]);
        
        $data = $response->json();
        $this->assertEquals(200, $data['code']);
        
        echo "\n✅ DAML-RAG系统指标测试通过\n";
    }
    
    /**
     * 测试流式监控端点
     * 
     * @test
     */
    public function test_daml_rag_streaming_endpoint()
    {
        $admin = $this->createAdmin();
        
        $response = $this->actingAs($admin)->getJson('/api/admin/metrics/daml-rag/streaming');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'code',
            'msg',
            'data'
        ]);
        
        $data = $response->json();
        $this->assertEquals(200, $data['code']);
        
        echo "\n✅ 流式监控测试通过\n";
    }
}
