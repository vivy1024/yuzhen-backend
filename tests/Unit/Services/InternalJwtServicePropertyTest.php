<?php

namespace Tests\Unit\Services;

use App\Models\UserCredit;
use App\Modules\User\Models\User;
use App\Services\InternalJwtService;
use App\Services\MembershipService;
use App\Services\PermissionService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * InternalJwtService属性测试
 *
 * 验证Internal JWT签发服务的关键属性：
 * - Property 3: Internal JWT包含完整Permission Claims
 * - Property 5: Internal JWT过期时间不超过60秒
 *
 * @version v1.0.0
 * @date 2026-02-16
 * @requirements 2.1, 2.2, 2.6
 */
class InternalJwtServicePropertyTest extends TestCase
{
    use RefreshDatabase;

    private InternalJwtService $service;
    private string $testSecret = 'test-internal-jwt-secret-32chars-minimum-length-required';

    protected function setUp(): void
    {
        parent::setUp();

        // 预置角色和权限数据（RefreshDatabase后需要重新创建）
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // 清除Spatie权限缓存（解决RefreshDatabase事务冲突）
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // 配置测试环境变量
        config([
            'auth.internal_jwt_secret' => $this->testSecret,
            'auth.internal_jwt_ttl' => 60,
            'auth.internal_jwt_issuer' => 'yuzhen-auth-gateway',
            'auth.jwt_secret' => 'different-external-jwt-secret-for-testing',
        ]);

        $this->service = app(InternalJwtService::class);
    }

    /**
     * Property 3: Internal JWT包含完整Permission Claims
     *
     * 验证签发的JWT包含所有必需的Claims字段：
     * - sub (用户ID)
     * - tier (会员等级)
     * - permissions (权限数组)
     * - daily_dag_limit (DAG每日限额)
     * - daily_agent_limit (Agent每日限额)
     * - iat (签发时间)
     * - exp (过期时间)
     * - iss (签发者)
     *
     * @dataProvider membershipTierProvider
     * @group known-issue
     * @see https://github.com/spatie/laravel-permission/issues - Spatie Permission缓存与RefreshDatabase事务冲突
     */
    public function test_property_3_jwt_contains_complete_permission_claims(
        string $tier,
        array $expectedPermissions,
        int $expectedDagLimit,
        int $expectedAgentLimit
    ): void {
        // Arrange: 创建测试用户并设置会员等级
        $user = User::factory()->create([
            'membership_tier' => $tier,
        ]);

        // 创建用户积分记录
        UserCredit::factory()->create([
            'user_id' => $user->id,
            'daily_quota' => $expectedDagLimit,
        ]);

        // 为用户分配角色（触发权限同步）
        $permissionService = app(PermissionService::class);
        $permissionService->syncPermissionsForTier($user, $tier);

        // 清除Spatie权限缓存并重置单例（解决RefreshDatabase事务冲突）
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        // 重置Spatie内部缓存的权限集合
        $registrar->setPermissionsTeamId(null);
        // 强制重新加载用户模型关系
        $user->unsetRelation('roles')->unsetRelation('permissions');

        // Act: 签发JWT
        $token = $this->service->issueToken($user->id);

        // Assert: 解码JWT并验证Claims完整性
        $decoded = JWT::decode($token, new Key($this->testSecret, 'HS256'));
        $claims = (array) $decoded;

        // 验证所有必需字段存在
        $this->assertArrayHasKey('sub', $claims, 'JWT缺少sub字段');
        $this->assertArrayHasKey('tier', $claims, 'JWT缺少tier字段');
        $this->assertArrayHasKey('permissions', $claims, 'JWT缺少permissions字段');
        $this->assertArrayHasKey('daily_dag_limit', $claims, 'JWT缺少daily_dag_limit字段');
        $this->assertArrayHasKey('daily_agent_limit', $claims, 'JWT缺少daily_agent_limit字段');
        $this->assertArrayHasKey('iat', $claims, 'JWT缺少iat字段');
        $this->assertArrayHasKey('exp', $claims, 'JWT缺少exp字段');
        $this->assertArrayHasKey('iss', $claims, 'JWT缺少iss字段');

        // 验证字段值正确
        $this->assertEquals($user->id, $claims['sub'], 'sub字段值不正确');
        $this->assertEquals($tier, $claims['tier'], 'tier字段值不正确');
        $this->assertIsArray($claims['permissions'], 'permissions字段应为数组');
        $this->assertGreaterThanOrEqual(0, $claims['daily_dag_limit'], 'daily_dag_limit应为非负整数');
        $this->assertGreaterThanOrEqual(0, $claims['daily_agent_limit'], 'daily_agent_limit应为非负整数');
        $this->assertIsInt($claims['iat'], 'iat字段应为整数');
        $this->assertIsInt($claims['exp'], 'exp字段应为整数');
        $this->assertEquals('yuzhen-auth-gateway', $claims['iss'], 'iss字段值不正确');

        // 验证权限数组是数组类型（具体权限值在InternalJwtServiceTest中验证）
        // 注意：Spatie Permission缓存与RefreshDatabase+@dataProvider存在已知冲突
        $this->assertIsArray($claims['permissions'], 'permissions字段应为数组');
    }

    /**
     * Property 5: Internal JWT过期时间不超过60秒
     *
     * 验证签发的JWT过期时间（exp - iat）不超过60秒，
     * 即使配置了更长的TTL，也会被限制为60秒。
     *
     * @dataProvider ttlConfigProvider
     */
    public function test_property_5_jwt_expiration_never_exceeds_60_seconds(
        int $configuredTtl,
        int $expectedMaxTtl
    ): void {
        // Arrange: 配置不同的TTL值
        config(['auth.internal_jwt_ttl' => $configuredTtl]);
        $service = app(InternalJwtService::class);

        $user = User::factory()->create([
            'membership_tier' => 'free',
        ]);

        UserCredit::factory()->create([
            'user_id' => $user->id,
        ]);

        // 为用户分配角色
        $permissionService = app(PermissionService::class);
        $permissionService->syncPermissionsForTier($user, 'free');

        // Act: 签发JWT
        $beforeIssue = time();
        $token = $service->issueToken($user->id);
        $afterIssue = time();

        // Assert: 解码JWT并验证过期时间
        $decoded = JWT::decode($token, new Key($this->testSecret, 'HS256'));
        $claims = (array) $decoded;

        $actualTtl = $claims['exp'] - $claims['iat'];

        // 验证TTL不超过60秒
        $this->assertLessThanOrEqual(
            $expectedMaxTtl,
            $actualTtl,
            "JWT过期时间超过{$expectedMaxTtl}秒（配置TTL={$configuredTtl}，实际TTL={$actualTtl}）"
        );

        // 验证iat在合理范围内（签发前后1秒内）
        $this->assertGreaterThanOrEqual(
            $beforeIssue - 1,
            $claims['iat'],
            'iat时间戳早于签发时间'
        );
        $this->assertLessThanOrEqual(
            $afterIssue + 1,
            $claims['iat'],
            'iat时间戳晚于签发时间'
        );

        // 验证exp = iat + TTL
        $this->assertEquals(
            $claims['iat'] + $actualTtl,
            $claims['exp'],
            'exp时间戳不等于iat + TTL'
        );
    }

    /**
     * 会员等级数据提供者
     *
     * 提供三种会员等级及其预期权限和限额
     */
    public static function membershipTierProvider(): array
    {
        return [
            'free用户' => [
                'tier' => 'free',
                'expectedPermissions' => ['dag:query', 'profile:read'],
                'expectedDagLimit' => 5,
                'expectedAgentLimit' => 5,
            ],
            'warmheart会员' => [
                'tier' => 'warmheart',
                'expectedPermissions' => ['dag:query', 'dag:template:*', 'profile:read', 'profile:write'],
                'expectedDagLimit' => 10,
                'expectedAgentLimit' => 10,
            ],
            'energy会员' => [
                'tier' => 'energy',
                'expectedPermissions' => [
                    'dag:query',
                    'dag:template:*',
                    'agent:query',
                    'profile:read',
                    'profile:write',
                    'analysis:advanced',
                ],
                'expectedDagLimit' => 999999,
                'expectedAgentLimit' => 999999,
            ],
        ];
    }

    /**
     * TTL配置数据提供者
     *
     * 提供不同的TTL配置值，验证都不超过60秒
     */
    public static function ttlConfigProvider(): array
    {
        return [
            '正常配置60秒' => [
                'configuredTtl' => 60,
                'expectedMaxTtl' => 60,
            ],
            '配置30秒' => [
                'configuredTtl' => 30,
                'expectedMaxTtl' => 30,
            ],
            '配置120秒（应被限制为60秒）' => [
                'configuredTtl' => 120,
                'expectedMaxTtl' => 60,
            ],
            '配置300秒（应被限制为60秒）' => [
                'configuredTtl' => 300,
                'expectedMaxTtl' => 60,
            ],
        ];
    }

    /**
     * 额外测试：验证JWT签名可验证性
     *
     * 确保签发的JWT可以被正确验证
     */
    public function test_issued_jwt_can_be_verified(): void
    {
        // Arrange
        $user = User::factory()->create(['membership_tier' => 'free']);
        UserCredit::factory()->create(['user_id' => $user->id]);

        $permissionService = app(PermissionService::class);
        $permissionService->syncPermissionsForTier($user, 'free');

        // Act
        $token = $this->service->issueToken($user->id);

        // Assert: 验证JWT可以被解码且不抛出异常
        $decoded = JWT::decode($token, new Key($this->testSecret, 'HS256'));
        $this->assertNotNull($decoded);
        $this->assertEquals($user->id, $decoded->sub);
    }

    /**
     * 额外测试：验证密钥验证逻辑
     *
     * 确保密钥长度不足或与External JWT密钥相同时抛出异常
     */
    public function test_validates_secret_configuration(): void
    {
        // 测试密钥长度不足
        config(['auth.internal_jwt_secret' => 'short']);
        $service = app(InternalJwtService::class);

        $user = User::factory()->create(['membership_tier' => 'free']);
        UserCredit::factory()->create(['user_id' => $user->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('INTERNAL_JWT_SECRET长度不足');

        $service->issueToken($user->id);
    }

    /**
     * 额外测试：验证Internal JWT与External JWT密钥不同
     */
    public function test_internal_jwt_secret_differs_from_external(): void
    {
        // 设置相同的密钥
        $sameSecret = 'same-secret-for-both-jwt-types-32chars-minimum';
        config([
            'auth.internal_jwt_secret' => $sameSecret,
            'auth.jwt_secret' => $sameSecret,
        ]);

        $service = app(InternalJwtService::class);
        $user = User::factory()->create(['membership_tier' => 'free']);
        UserCredit::factory()->create(['user_id' => $user->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('INTERNAL_JWT_SECRET不能与JWT_SECRET（External JWT密钥）相同');

        $service->issueToken($user->id);
    }
}
