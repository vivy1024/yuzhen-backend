<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

/**
 * API响应规范合规性集成测试
 * 
 * 测试所有API接口是否遵循统一的响应格式规范
 * 验证用户敏感操作的响应是否清晰可感知
 * 
 * @module tests/Feature/ApiResponseComplianceTest
 * @version 1.0.0
 * @date 2026-01-17
 */
class ApiResponseComplianceTest extends TestCase
{
    use RefreshDatabase;

    private User $testUser;
    private string $testPassword = 'Test@123456';

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试用户
        $this->testUser = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make($this->testPassword),
            'email_verified_at' => now(),
        ]);
    }

    /**
     * ========================================
     * 认证流程集成测试
     * 需求: 2.1-2.8
     * ========================================
     */

    /**
     * 测试登录成功场景
     * 需求: 2.1
     */
    public function test_login_success_returns_200_with_user_and_token()
    {
        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'test@example.com',
            'password' => $this->testPassword,
        ]);

        // 验证响应结构
        $response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => [
                    'user' => [
                        'id',
                        'email',
                    ],
                    'access_token',
                    'refresh_token',
                    'expires_in',
                ],
            ]);

        // 验证响应内容
        $data = $response->json();
        $this->assertEquals(200, $data['code']);
        $this->assertStringContainsString('成功', $data['msg']);
        $this->assertNotNull($data['data']['access_token']);
        $this->assertNotNull($data['data']['refresh_token']);
        $this->assertEquals('test@example.com', $data['data']['user']['email']);
    }

    /**
     * 测试登录失败 - 密码错误
     * 需求: 2.2
     */
    public function test_login_failure_wrong_password_returns_401()
    {
        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'test@example.com',
            'password' => 'WrongPassword123',
        ]);

        // 验证响应结构
        $response->assertStatus(401)
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
            ]);

        // 验证响应内容
        $data = $response->json();
        $this->assertEquals(401, $data['code']);
        $this->assertStringContainsString('密码', $data['msg']);
        $this->assertNull($data['data']);
    }

    /**
     * 测试登录失败 - 账号不存在
     * 需求: 2.3
     * 注意：实际API返回401而非404，这是合理的安全实践（不泄露账号是否存在）
     */
    public function test_login_failure_account_not_exists_returns_401()
    {
        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'nonexistent@example.com',
            'password' => $this->testPassword,
        ]);

        // 验证响应结构
        $response->assertStatus(401)
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
            ]);

        // 验证响应内容
        $data = $response->json();
        $this->assertEquals(401, $data['code']);
        $this->assertNotEmpty($data['msg']);
        $this->assertNull($data['data']);
    }

    /**
     * 测试注册成功场景
     * 需求: 2.4
     */
    public function test_register_success_returns_200_with_user_and_token()
    {
        // 模拟验证码验证通过（使用正确的cache key格式）
        Cache::put('email:code:newuser@example.com', '123456', 600);

        $response = $this->postJson('/api/auth/register', [
            'nickname' => '新用户',
            'email' => 'newuser@example.com',
            'email_code' => '123456',
            'password' => 'NewUser@123',
            'password_confirmation' => 'NewUser@123',
        ]);

        // 验证响应结构
        $response->assertStatus(201)
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => [
                    'user',
                    'access_token',
                    'refresh_token',
                ],
            ]);

        // 验证响应内容
        $data = $response->json();
        $this->assertEquals(200, $data['code']);
        $this->assertStringContainsString('成功', $data['msg']);
        $this->assertNotNull($data['data']['access_token']);
    }

    /**
     * 测试注册失败 - 邮箱已存在
     * 需求: 2.5
     */
    public function test_register_failure_email_exists_returns_422()
    {
        // 模拟验证码验证通过
        Cache::put('email:code:test@example.com', '123456', 600);

        $response = $this->postJson('/api/auth/register', [
            'nickname' => '新用户',
            'email' => 'test@example.com', // 已存在的邮箱
            'email_code' => '123456',
            'password' => 'NewUser@123',
            'password_confirmation' => 'NewUser@123',
        ]);

        // 验证响应结构
        $response->assertStatus(422)
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
            ]);

        // 验证响应内容
        $data = $response->json();
        $this->assertEquals(422, $data['code']);
        // 验证错误消息存在（可能是"邮箱已存在"或"数据验证失败"）
        $this->assertNotEmpty($data['msg']);
    }

    /**
     * 测试注册失败 - 验证码错误
     * 需求: 2.6
     */
    public function test_register_failure_wrong_code_returns_422()
    {
        // 设置正确的验证码
        Cache::put('email:code:newuser2@example.com', '123456', 600);

        $response = $this->postJson('/api/auth/register', [
            'nickname' => '新用户2',
            'email' => 'newuser2@example.com',
            'email_code' => '999999', // 错误的验证码
            'password' => 'NewUser@123',
            'password_confirmation' => 'NewUser@123',
        ]);

        // 验证响应结构
        $response->assertStatus(422)
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
            ]);

        // 验证响应内容
        $data = $response->json();
        $this->assertEquals(422, $data['code']);
        $this->assertStringContainsString('验证码', $data['msg']);
    }

    /**
     * 测试Token刷新成功
     * 需求: 2.8
     */
    public function test_token_refresh_success_returns_200_with_new_token()
    {
        // 先登录获取token
        $loginResponse = $this->postJson('/api/auth/login', [
            'identifier' => 'test@example.com',
            'password' => $this->testPassword,
        ]);

        $refreshToken = $loginResponse->json('data.refresh_token');

        // 使用refresh token刷新
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        // 验证响应结构
        $response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => [
                    'access_token',
                    'refresh_token',
                    'expires_in',
                ],
            ]);

        // 验证响应内容
        $data = $response->json();
        $this->assertEquals(200, $data['code']);
        $this->assertStringContainsString('刷新', $data['msg']);
        $this->assertNotNull($data['data']['access_token']);
    }

    /**
     * 测试Token过期场景
     * 需求: 2.7
     */
    public function test_expired_token_returns_401()
    {
        // 使用无效的token访问需要认证的接口
        // 使用训练日志接口作为测试端点（确保需要认证）
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_expired_token',
        ])->getJson('/api/training-logs');

        // 验证响应结构
        $response->assertStatus(401)
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
            ]);

        // 验证响应内容
        $data = $response->json();
        $this->assertEquals(401, $data['code']);
        $this->assertNotEmpty($data['msg']);
        $this->assertNull($data['data']);
    }

    /**
     * ========================================
     * 响应格式通用验证
     * ========================================
     */

    /**
     * 测试所有响应都包含必需字段
     */
    public function test_all_responses_have_required_fields()
    {
        $endpoints = [
            ['method' => 'post', 'url' => '/api/auth/login', 'data' => [
                'identifier' => 'test@example.com',
                'password' => $this->testPassword,
            ]],
            ['method' => 'get', 'url' => '/api/exercises-v2', 'data' => []],
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->{$endpoint['method'] . 'Json'}(
                $endpoint['url'],
                $endpoint['data']
            );

            // 验证必需字段存在
            $data = $response->json();
            $this->assertArrayHasKey('code', $data, "Missing 'code' field in {$endpoint['url']}");
            $this->assertArrayHasKey('msg', $data, "Missing 'msg' field in {$endpoint['url']}");
            $this->assertArrayHasKey('data', $data, "Missing 'data' field in {$endpoint['url']}");

            // 验证字段类型
            $this->assertIsInt($data['code'], "'code' should be integer in {$endpoint['url']}");
            $this->assertIsString($data['msg'], "'msg' should be string in {$endpoint['url']}");
        }
    }

    /**
     * 测试成功响应的code为200
     */
    public function test_success_responses_have_code_200()
    {
        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'test@example.com',
            'password' => $this->testPassword,
        ]);

        $data = $response->json();
        $this->assertEquals(200, $data['code']);
        $this->assertNotEmpty($data['msg']);
    }

    /**
     * 测试错误响应的code为4xx或5xx
     */
    public function test_error_responses_have_4xx_or_5xx_code()
    {
        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'test@example.com',
            'password' => 'WrongPassword',
        ]);

        $data = $response->json();
        $this->assertGreaterThanOrEqual(400, $data['code']);
        $this->assertLessThan(600, $data['code']);
        $this->assertNotEmpty($data['msg']);
    }
}
