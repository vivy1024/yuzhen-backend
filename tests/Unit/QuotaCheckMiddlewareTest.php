<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Infrastructure\Http\Middleware\QuotaCheck;
use App\Services\UsageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Mockery;

/**
 * QuotaCheck中间件 单元测试
 * 
 * 测试配额检查中间件的核心逻辑
 * 
 * @version v1.0.0
 * @date 2026-02-11
 * @requirements 4.1, 4.2
 */
class QuotaCheckMiddlewareTest extends TestCase
{
    private QuotaCheck $middleware;
    private $mockUsageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockUsageService = Mockery::mock(UsageService::class);
        $this->middleware = new QuotaCheck($this->mockUsageService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 创建带认证用户的模拟请求
     */
    private function createAuthenticatedRequest(array $body = [], ?object $user = null): Request
    {
        $request = Request::create('/api/ai/v1/chat/stream', 'POST', $body, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($body));

        if ($user) {
            $request->setUserResolver(fn() => $user);
        }

        return $request;
    }

    /**
     * 创建模拟用户对象
     */
    private function createMockUser(int $id = 1): object
    {
        $user = new \stdClass();
        $user->id = $id;
        return $user;
    }

    /**
     * 通过中间件的next回调
     */
    private function passThrough(): \Closure
    {
        return fn(Request $request) => new JsonResponse(['code' => 200, 'msg' => 'ok'], 200);
    }

    // ==================== 未认证用户测试 ====================

    /**
     * 测试未认证用户返回401
     */
    public function test_unauthenticated_user_returns_401(): void
    {
        $request = $this->createAuthenticatedRequest(['strategy' => 'dag']);

        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(401, $data['code']);
    }

    // ==================== 配额充足测试 ====================

    /**
     * 测试配额充足时放行请求（dag模式）
     */
    public function test_allows_request_when_dag_quota_available(): void
    {
        $user = $this->createMockUser(1);
        $request = $this->createAuthenticatedRequest(['strategy' => 'dag'], $user);

        $this->mockUsageService->shouldReceive('canExecuteQuery')
            ->with(1, 'dag')
            ->once()
            ->andReturn([
                'allowed' => true,
                'remaining' => 7,
                'use_credits' => false,
                'message' => '可以执行查询（剩余7次）',
            ]);

        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * 测试配额充足时放行请求（agent模式）
     */
    public function test_allows_request_when_agent_quota_available(): void
    {
        $user = $this->createMockUser(2);
        $request = $this->createAuthenticatedRequest(['strategy' => 'agent'], $user);

        $this->mockUsageService->shouldReceive('canExecuteQuery')
            ->with(2, 'agent')
            ->once()
            ->andReturn([
                'allowed' => true,
                'remaining' => 3,
                'use_credits' => false,
                'message' => '可以执行查询（剩余3次）',
            ]);

        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ==================== 配额不足测试 ====================

    /**
     * 测试配额不足返回429（dag模式）
     */
    public function test_returns_429_when_dag_quota_exhausted(): void
    {
        $user = $this->createMockUser(1);
        $request = $this->createAuthenticatedRequest(['strategy' => 'dag'], $user);

        $this->mockUsageService->shouldReceive('canExecuteQuery')
            ->with(1, 'dag')
            ->once()
            ->andReturn([
                'allowed' => false,
                'remaining' => 0,
                'use_credits' => false,
                'message' => '今日DAG查询次数已用完',
            ]);

        $this->mockUsageService->shouldReceive('getTodayUsage')
            ->with(1)
            ->once()
            ->andReturn([
                'dag_used' => 5,
                'dag_limit' => 5,
                'dag_remaining' => 0,
                'agent_used' => 0,
                'agent_limit' => 0,
                'agent_remaining' => 0,
                'dag_credits' => 0,
                'agent_credits' => 0,
                'date' => '2026-02-11',
            ]);

        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertEquals(429, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(429, $data['code']);
        $this->assertFalse($data['data']['allowed']);
        $this->assertEquals('dag', $data['data']['mode']);
        $this->assertEquals(0, $data['data']['remaining']);
        $this->assertEquals(5, $data['data']['daily_limit']);
        $this->assertEquals(5, $data['data']['used_count']);
    }

    /**
     * 测试配额不足返回429（agent模式）
     */
    public function test_returns_429_when_agent_quota_exhausted(): void
    {
        $user = $this->createMockUser(1);
        $request = $this->createAuthenticatedRequest(['strategy' => 'agent'], $user);

        $this->mockUsageService->shouldReceive('canExecuteQuery')
            ->with(1, 'agent')
            ->once()
            ->andReturn([
                'allowed' => false,
                'remaining' => 0,
                'use_credits' => false,
                'message' => '今日Agent查询次数已用完',
            ]);

        $this->mockUsageService->shouldReceive('getTodayUsage')
            ->with(1)
            ->once()
            ->andReturn([
                'dag_used' => 3,
                'dag_limit' => 5,
                'dag_remaining' => 2,
                'agent_used' => 3,
                'agent_limit' => 3,
                'agent_remaining' => 0,
                'dag_credits' => 0,
                'agent_credits' => 0,
                'date' => '2026-02-11',
            ]);

        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertEquals(429, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('agent', $data['data']['mode']);
        $this->assertEquals(3, $data['data']['daily_limit']);
        $this->assertEquals(3, $data['data']['used_count']);
    }

    // ==================== strategy映射测试 ====================

    /**
     * 测试默认strategy映射为dag
     */
    public function test_default_strategy_maps_to_dag(): void
    {
        $user = $this->createMockUser(1);
        $request = $this->createAuthenticatedRequest([], $user); // 无strategy字段

        $this->mockUsageService->shouldReceive('canExecuteQuery')
            ->with(1, 'dag')
            ->once()
            ->andReturn(['allowed' => true, 'remaining' => 5, 'use_credits' => false, 'message' => 'ok']);

        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * 测试agent_query策略映射为agent
     */
    public function test_agent_query_strategy_maps_to_agent(): void
    {
        $user = $this->createMockUser(1);
        $request = $this->createAuthenticatedRequest(['strategy' => 'agent_query'], $user);

        $this->mockUsageService->shouldReceive('canExecuteQuery')
            ->with(1, 'agent')
            ->once()
            ->andReturn(['allowed' => true, 'remaining' => 2, 'use_credits' => false, 'message' => 'ok']);

        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ==================== 异常处理测试 ====================

    /**
     * 测试UsageService异常时返回500（fail-closed）
     */
    public function test_returns_500_when_usage_service_throws(): void
    {
        $user = $this->createMockUser(1);
        $request = $this->createAuthenticatedRequest(['strategy' => 'dag'], $user);

        $this->mockUsageService->shouldReceive('canExecuteQuery')
            ->with(1, 'dag')
            ->once()
            ->andThrow(new \RuntimeException('数据库连接失败'));

        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(500, $data['code']);
        $this->assertStringContains('配额检查服务异常', $data['msg']);
    }

    /**
     * 辅助断言：字符串包含
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
