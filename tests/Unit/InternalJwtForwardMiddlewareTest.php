<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Infrastructure\Http\Middleware\InternalJwtForward;
use App\Services\InternalJwtService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Mockery;

/**
 * InternalJwtForward中间件 单元测试
 * 
 * 测试Internal JWT转发中间件的核心逻辑：
 * - 开关开启时签发JWT附加到Authorization头
 * - 开关关闭时使用X-Internal-Token
 * - JWT签发失败时降级到X-Internal-Token
 * 
 * @version v1.0.0
 * @date 2026-02-11
 * @requirements 2.1, 7.3
 */
class InternalJwtForwardMiddlewareTest extends TestCase
{
    private InternalJwtForward $middleware;
    private $mockJwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockJwtService = Mockery::mock(InternalJwtService::class);
        $this->middleware = new InternalJwtForward($this->mockJwtService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 创建带认证用户的请求
     */
    private function createAuthenticatedRequest(?object $user = null): Request
    {
        $request = Request::create('/api/ai/v1/chat/stream', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        if ($user) {
            $request->setUserResolver(fn() => $user);
        }

        return $request;
    }

    private function createMockUser(int $id = 1): object
    {
        $user = new \stdClass();
        $user->id = $id;
        return $user;
    }

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
        $request = $this->createAuthenticatedRequest();

        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(401, $data['code']);
    }

    // ==================== 开关开启 - JWT模式 ====================

    /**
     * 测试开关开启时签发JWT并附加到Authorization头
     */
    public function test_enabled_attaches_jwt_to_authorization_header(): void
    {
        config(['auth.enable_internal_jwt' => true]);
        config(['app.internal_api_token' => str_repeat('a', 32)]);

        $user = $this->createMockUser(42);
        $request = $this->createAuthenticatedRequest($user);

        $this->mockJwtService->shouldReceive('issueToken')
            ->with(42)
            ->once()
            ->andReturn('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.test.signature');

        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return new JsonResponse(['code' => 200], 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($capturedRequest);
        $this->assertEquals(
            'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.test.signature',
            $capturedRequest->headers->get('Authorization')
        );
    }

    /**
     * 测试开关开启但JWT签发失败时降级到X-Internal-Token
     */
    public function test_enabled_fallback_to_internal_token_on_jwt_failure(): void
    {
        $internalToken = str_repeat('b', 32);
        config(['auth.enable_internal_jwt' => true]);
        config(['app.internal_api_token' => $internalToken]);

        $user = $this->createMockUser(7);
        $request = $this->createAuthenticatedRequest($user);

        $this->mockJwtService->shouldReceive('issueToken')
            ->with(7)
            ->once()
            ->andThrow(new \InvalidArgumentException('INTERNAL_JWT_SECRET未配置'));

        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return new JsonResponse(['code' => 200], 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($capturedRequest);
        $this->assertEquals($internalToken, $capturedRequest->headers->get('X-Internal-Token'));
        // Authorization头不应被设置
        $this->assertNull($capturedRequest->headers->get('Authorization'));
    }

    // ==================== 开关关闭 - X-Internal-Token模式 ====================

    /**
     * 测试开关关闭时使用X-Internal-Token
     */
    public function test_disabled_attaches_internal_token(): void
    {
        $internalToken = str_repeat('c', 32);
        config(['auth.enable_internal_jwt' => false]);
        config(['app.internal_api_token' => $internalToken]);

        $user = $this->createMockUser(10);
        $request = $this->createAuthenticatedRequest($user);

        // JWT服务不应被调用
        $this->mockJwtService->shouldNotReceive('issueToken');

        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return new JsonResponse(['code' => 200], 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($capturedRequest);
        $this->assertEquals($internalToken, $capturedRequest->headers->get('X-Internal-Token'));
    }

    /**
     * 测试开关关闭且internal_api_token为空时不附加头
     */
    public function test_disabled_no_token_when_internal_api_token_empty(): void
    {
        config(['auth.enable_internal_jwt' => false]);
        config(['app.internal_api_token' => '']);

        $user = $this->createMockUser(5);
        $request = $this->createAuthenticatedRequest($user);

        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return new JsonResponse(['code' => 200], 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($capturedRequest->headers->get('X-Internal-Token'));
    }

    // ==================== 降级容错测试 ====================

    /**
     * 测试RuntimeException也能正确降级
     */
    public function test_enabled_fallback_on_runtime_exception(): void
    {
        $internalToken = str_repeat('d', 32);
        config(['auth.enable_internal_jwt' => true]);
        config(['app.internal_api_token' => $internalToken]);

        $user = $this->createMockUser(3);
        $request = $this->createAuthenticatedRequest($user);

        $this->mockJwtService->shouldReceive('issueToken')
            ->with(3)
            ->once()
            ->andThrow(new \RuntimeException('数据库连接失败'));

        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return new JsonResponse(['code' => 200], 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($internalToken, $capturedRequest->headers->get('X-Internal-Token'));
    }
}
