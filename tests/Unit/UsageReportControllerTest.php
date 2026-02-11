<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\Internal\UsageReportController;
use App\Services\UsageService;
use Illuminate\Http\Request;
use Mockery;

/**
 * UsageReportController 单元测试
 * 
 * 测试用量上报接收端点的核心逻辑
 * 
 * @version v1.0.0
 * @date 2026-01-18
 * @requirements 4.3
 */
class UsageReportControllerTest extends TestCase
{
    private UsageReportController $controller;
    private $mockUsageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockUsageService = Mockery::mock(UsageService::class);
        $this->controller = new UsageReportController($this->mockUsageService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 创建用量上报请求
     */
    private function createReportRequest(array $body = []): Request
    {
        $defaults = [
            'user_id' => 1,
            'mode' => 'dag',
            'session_id' => 'sess_abc123',
            'timestamp' => '2026-01-18T10:30:00Z',
            'execution_time_ms' => 1500,
        ];

        $data = array_merge($defaults, $body);

        return Request::create('/api/internal/usage/report', 'POST', $data, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($data));
    }

    // ==================== 成功上报测试 ====================

    /**
     * 测试DAG模式用量上报成功
     */
    public function test_report_dag_usage_success(): void
    {
        $request = $this->createReportRequest(['mode' => 'dag']);

        $this->mockUsageService->shouldReceive('incrementUsage')
            ->with(1, 'dag')
            ->once()
            ->andReturn([
                'success' => true,
                'new_count' => 3,
                'remaining' => 7,
                'message' => '查询成功（剩余7次）',
            ]);

        $response = $this->controller->report($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(1, $data['data']['user_id']);
        $this->assertEquals('dag', $data['data']['mode']);
        $this->assertEquals(3, $data['data']['new_count']);
        $this->assertEquals(7, $data['data']['remaining']);
    }

    /**
     * 测试Agent模式用量上报成功
     */
    public function test_report_agent_usage_success(): void
    {
        $request = $this->createReportRequest(['mode' => 'agent', 'user_id' => 5]);

        $this->mockUsageService->shouldReceive('incrementUsage')
            ->with(5, 'agent')
            ->once()
            ->andReturn([
                'success' => true,
                'new_count' => 1,
                'remaining' => 2,
                'message' => '查询成功（剩余2次）',
            ]);

        $response = $this->controller->report($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(5, $data['data']['user_id']);
        $this->assertEquals('agent', $data['data']['mode']);
    }

    // ==================== 参数验证测试 ====================

    /**
     * 测试缺少user_id返回400
     */
    public function test_missing_user_id_returns_400(): void
    {
        $request = $this->createReportRequest(['user_id' => null]);

        $response = $this->controller->report($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * 测试无效mode返回400
     */
    public function test_invalid_mode_returns_400(): void
    {
        $request = $this->createReportRequest(['mode' => 'invalid']);

        $response = $this->controller->report($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * 测试缺少session_id返回400
     */
    public function test_missing_session_id_returns_400(): void
    {
        $request = $this->createReportRequest(['session_id' => null]);

        $response = $this->controller->report($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * 测试缺少timestamp返回400
     */
    public function test_missing_timestamp_returns_400(): void
    {
        $request = $this->createReportRequest(['timestamp' => null]);

        $response = $this->controller->report($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * 测试execution_time_ms为负数返回400
     */
    public function test_negative_execution_time_returns_400(): void
    {
        $request = $this->createReportRequest(['execution_time_ms' => -1]);

        $response = $this->controller->report($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    // ==================== UsageService失败测试 ====================

    /**
     * 测试UsageService返回失败时返回500
     */
    public function test_returns_500_when_usage_service_fails(): void
    {
        $request = $this->createReportRequest();

        $this->mockUsageService->shouldReceive('incrementUsage')
            ->with(1, 'dag')
            ->once()
            ->andReturn([
                'success' => false,
                'new_count' => 0,
                'remaining' => 0,
                'message' => '额度不足，无法执行查询',
            ]);

        $response = $this->controller->report($request);

        $this->assertEquals(500, $response->getStatusCode());
    }

    /**
     * 测试UsageService抛出异常时返回500
     */
    public function test_returns_500_when_usage_service_throws(): void
    {
        $request = $this->createReportRequest();

        $this->mockUsageService->shouldReceive('incrementUsage')
            ->with(1, 'dag')
            ->once()
            ->andThrow(new \RuntimeException('数据库连接失败'));

        $response = $this->controller->report($request);

        $this->assertEquals(500, $response->getStatusCode());
    }
}
