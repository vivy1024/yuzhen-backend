<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\InternalJwtService;
use App\Services\PermissionService;
use App\Services\MembershipService;
use App\Modules\User\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\RolePermissionSeeder;

/**
 * InternalJwtService 单元测试
 * 
 * 测试内部JWT签发和Claims构建功能
 * 
 * @version v1.0.0
 * @date 2026-02-11
 * @requirements 2.1, 2.2, 2.3, 2.6
 */
class InternalJwtServiceTest extends TestCase
{
    use RefreshDatabase;

    private InternalJwtService $service;
    private string $testSecret = 'TestInternalJwtSecret2026AtLeast32Chars!!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // 清除Spatie权限缓存（解决RefreshDatabase事务冲突）
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // 配置Internal JWT环境变量
        config([
            'auth.internal_jwt_secret' => $this->testSecret,
            'auth.internal_jwt_ttl' => 60,
            'auth.internal_jwt_issuer' => 'yuzhen-auth-gateway',
            'auth.jwt_secret' => 'DifferentExternalJwtSecretForTesting123',
        ]);

        $this->service = app(InternalJwtService::class);
    }

    private function createTestUser(string $tier = 'free'): User
    {
        $user = User::create([
            'name' => 'Test User ' . uniqid(),
            'email' => 'test' . uniqid() . '@example.com',
            'password' => Hash::make('Test@123456'),
            'email_verified_at' => now(),
            'membership_tier' => $tier,
        ]);

        // 同步Spatie角色
        app(PermissionService::class)->syncPermissionsForTier($user, $tier);

        return $user;
    }

    // ==================== buildClaims() 测试 ====================

    /**
     * 测试free用户的Claims包含所有必要字段
     */
    public function test_build_claims_contains_all_required_fields(): void
    {
        $user = $this->createTestUser('free');
        $claims = $this->service->buildClaims($user->id);

        $this->assertArrayHasKey('sub', $claims);
        $this->assertArrayHasKey('tier', $claims);
        $this->assertArrayHasKey('permissions', $claims);
        $this->assertArrayHasKey('daily_dag_limit', $claims);
        $this->assertArrayHasKey('daily_agent_limit', $claims);
        $this->assertArrayHasKey('iat', $claims);
        $this->assertArrayHasKey('exp', $claims);
        $this->assertArrayHasKey('iss', $claims);
    }

    /**
     * 测试free用户Claims值正确（会员系统启用时）
     */
    public function test_build_claims_free_user(): void
    {
        config(['membership.enabled' => true]);
        $user = $this->createTestUser('free');
        $claims = $this->service->buildClaims($user->id);

        $this->assertEquals($user->id, $claims['sub']);
        $this->assertEquals('free', $claims['tier']);
        $this->assertEquals('yuzhen-auth-gateway', $claims['iss']);
        $this->assertEquals(5, $claims['daily_dag_limit']);
        $this->assertEquals(5, $claims['daily_agent_limit']);

        $permissions = $claims['permissions'];
        sort($permissions);
        $this->assertEquals(['dag:query', 'profile:read'], $permissions);
    }

    /**
     * 测试warmheart用户Claims值正确（会员系统启用时）
     */
    public function test_build_claims_warmheart_user(): void
    {
        config(['membership.enabled' => true]);
        $user = $this->createTestUser('warmheart');
        $claims = $this->service->buildClaims($user->id);

        $this->assertEquals($user->id, $claims['sub']);
        $this->assertEquals('warmheart', $claims['tier']);
        $this->assertEquals(10, $claims['daily_dag_limit']);
        $this->assertEquals(10, $claims['daily_agent_limit']);

        $permissions = $claims['permissions'];
        sort($permissions);
        $this->assertEquals(
            ['dag:query', 'dag:template:*', 'profile:read', 'profile:write'],
            $permissions
        );
    }

    /**
     * 测试energy用户Claims值正确（会员系统启用时）
     */
    public function test_build_claims_energy_user(): void
    {
        config(['membership.enabled' => true]);
        $user = $this->createTestUser('energy');
        $claims = $this->service->buildClaims($user->id);

        $this->assertEquals($user->id, $claims['sub']);
        $this->assertEquals('energy', $claims['tier']);
        $this->assertEquals(999999, $claims['daily_dag_limit']);
        $this->assertEquals(999999, $claims['daily_agent_limit']);

        $permissions = $claims['permissions'];
        sort($permissions);
        $this->assertEquals(
            ['agent:query', 'analysis:advanced', 'dag:query', 'dag:template:*', 'profile:read', 'profile:write'],
            $permissions
        );
    }

    /**
     * 测试会员系统未启用时使用开发限制
     */
    public function test_build_claims_uses_dev_limits_when_membership_disabled(): void
    {
        config(['membership.enabled' => false]);
        $user = $this->createTestUser('free');
        $claims = $this->service->buildClaims($user->id);

        // 会员系统关闭时，getEffectiveLimits返回DEV_LIMITS
        $this->assertEquals(10, $claims['daily_dag_limit']);
        $this->assertEquals(3, $claims['daily_agent_limit']);
    }

    /**
     * 测试Claims的exp - iat不超过60秒
     */
    public function test_build_claims_expiry_within_60_seconds(): void
    {
        $user = $this->createTestUser('free');
        $claims = $this->service->buildClaims($user->id);

        $diff = $claims['exp'] - $claims['iat'];
        $this->assertGreaterThan(0, $diff);
        $this->assertLessThanOrEqual(60, $diff);
    }

    /**
     * 测试不存在的用户抛出异常
     */
    public function test_build_claims_nonexistent_user_throws(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->buildClaims(99999);
    }

    // ==================== issueToken() 测试 ====================

    /**
     * 测试签发的JWT可以用相同密钥解码
     */
    public function test_issue_token_can_be_decoded(): void
    {
        $user = $this->createTestUser('warmheart');
        $token = $this->service->issueToken($user->id);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // 用相同密钥解码验证
        $decoded = JWT::decode($token, new Key($this->testSecret, 'HS256'));

        $this->assertEquals($user->id, $decoded->sub);
        $this->assertEquals('warmheart', $decoded->tier);
        $this->assertEquals('yuzhen-auth-gateway', $decoded->iss);
    }

    /**
     * 测试签发的JWT包含完整Claims
     */
    public function test_issue_token_contains_complete_claims(): void
    {
        $user = $this->createTestUser('energy');
        $token = $this->service->issueToken($user->id);

        $decoded = JWT::decode($token, new Key($this->testSecret, 'HS256'));

        $this->assertEquals($user->id, $decoded->sub);
        $this->assertEquals('energy', $decoded->tier);
        $this->assertIsArray($decoded->permissions);
        $this->assertIsInt($decoded->daily_dag_limit);
        $this->assertIsInt($decoded->daily_agent_limit);
        $this->assertIsInt($decoded->iat);
        $this->assertIsInt($decoded->exp);
        $this->assertEquals('yuzhen-auth-gateway', $decoded->iss);
    }

    /**
     * 测试用错误密钥无法解码JWT
     */
    public function test_issue_token_cannot_decode_with_wrong_key(): void
    {
        $user = $this->createTestUser('free');
        $token = $this->service->issueToken($user->id);

        $this->expectException(\Firebase\JWT\SignatureInvalidException::class);
        JWT::decode($token, new Key('wrong-secret-key-that-is-32chars!!', 'HS256'));
    }

    // ==================== 密钥验证测试 ====================

    /**
     * 测试空密钥抛出异常
     */
    public function test_issue_token_throws_when_secret_empty(): void
    {
        config(['auth.internal_jwt_secret' => '']);
        $service = app(InternalJwtService::class);

        $user = $this->createTestUser('free');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('INTERNAL_JWT_SECRET未配置');
        $service->issueToken($user->id);
    }

    /**
     * 测试密钥长度不足抛出异常
     */
    public function test_issue_token_throws_when_secret_too_short(): void
    {
        config(['auth.internal_jwt_secret' => 'short']);
        $service = app(InternalJwtService::class);

        $user = $this->createTestUser('free');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('长度不足');
        $service->issueToken($user->id);
    }

    /**
     * 测试Internal JWT密钥与External JWT密钥相同时抛出异常
     */
    public function test_issue_token_throws_when_same_as_external_secret(): void
    {
        $sameSecret = 'SharedSecretThatIs32CharsLong!!!X';
        config([
            'auth.internal_jwt_secret' => $sameSecret,
            'auth.jwt_secret' => $sameSecret,
        ]);
        $service = app(InternalJwtService::class);

        $user = $this->createTestUser('free');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('不能与JWT_SECRET');
        $service->issueToken($user->id);
    }

    // ==================== TTL配置测试 ====================

    /**
     * 测试TTL超过60秒时被强制限制为60
     */
    public function test_ttl_capped_at_60_seconds(): void
    {
        config(['auth.internal_jwt_ttl' => 120]);
        $service = app(InternalJwtService::class);

        $this->assertEquals(60, $service->getTtl());
    }

    /**
     * 测试getter方法
     */
    public function test_getter_methods(): void
    {
        $this->assertEquals(60, $this->service->getTtl());
        $this->assertEquals('yuzhen-auth-gateway', $this->service->getIssuer());
    }
}
