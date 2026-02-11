<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PermissionService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\RolePermissionSeeder;

/**
 * PermissionService 单元测试
 * 
 * 测试角色权限同步和权限查询功能
 * 
 * @version v1.0.0
 * @date 2026-02-11
 * @requirements 3.3, 3.4
 */
class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->service = new PermissionService();
    }

    private function createTestUser(string $tier = 'free'): User
    {
        return User::create([
            'name' => 'Test User ' . uniqid(),
            'email' => 'test' . uniqid() . '@example.com',
            'password' => Hash::make('Test@123456'),
            'email_verified_at' => now(),
            'membership_tier' => $tier,
        ]);
    }

    /**
     * 测试free等级同步正确的角色和权限
     */
    public function test_sync_permissions_for_free_tier(): void
    {
        $user = $this->createTestUser('free');

        $this->service->syncPermissionsForTier($user, 'free');

        $this->assertTrue($user->hasRole('free'));
        $this->assertFalse($user->hasRole('warmheart'));
        $this->assertFalse($user->hasRole('energy'));

        $permissions = $this->service->getUserPermissions($user->id);
        sort($permissions);
        $this->assertEquals(['dag:query', 'profile:read'], $permissions);
    }

    /**
     * 测试warmheart等级同步正确的角色和权限
     */
    public function test_sync_permissions_for_warmheart_tier(): void
    {
        $user = $this->createTestUser('warmheart');

        $this->service->syncPermissionsForTier($user, 'warmheart');

        $this->assertTrue($user->hasRole('warmheart'));
        $this->assertFalse($user->hasRole('free'));

        $permissions = $this->service->getUserPermissions($user->id);
        sort($permissions);
        $this->assertEquals(
            ['dag:query', 'dag:template:*', 'profile:read', 'profile:write'],
            $permissions
        );
    }

    /**
     * 测试energy等级同步正确的角色和权限
     */
    public function test_sync_permissions_for_energy_tier(): void
    {
        $user = $this->createTestUser('energy');

        $this->service->syncPermissionsForTier($user, 'energy');

        $this->assertTrue($user->hasRole('energy'));

        $permissions = $this->service->getUserPermissions($user->id);
        sort($permissions);
        $this->assertEquals(
            ['agent:query', 'analysis:advanced', 'dag:query', 'dag:template:*', 'profile:read', 'profile:write'],
            $permissions
        );
    }

    /**
     * 测试等级升级时旧角色被移除
     */
    public function test_sync_replaces_old_role(): void
    {
        $user = $this->createTestUser('free');

        $this->service->syncPermissionsForTier($user, 'free');
        $this->assertTrue($user->hasRole('free'));

        // 升级到warmheart
        $this->service->syncPermissionsForTier($user, 'warmheart');
        $user->refresh();

        $this->assertTrue($user->hasRole('warmheart'));
        $this->assertFalse($user->hasRole('free'));
    }

    /**
     * 测试无效等级不会修改角色
     */
    public function test_sync_with_invalid_tier_does_nothing(): void
    {
        $user = $this->createTestUser('free');
        $this->service->syncPermissionsForTier($user, 'free');

        // 尝试无效等级
        $this->service->syncPermissionsForTier($user, 'invalid_tier');
        $user->refresh();

        // 应保持原有角色
        $this->assertTrue($user->hasRole('free'));
    }

    /**
     * 测试getUserPermissions对不存在的用户返回空数组
     */
    public function test_get_permissions_for_nonexistent_user(): void
    {
        $permissions = $this->service->getUserPermissions(99999);
        $this->assertEquals([], $permissions);
    }

    /**
     * 测试getUserPermissions对无角色用户返回空数组
     */
    public function test_get_permissions_for_user_without_role(): void
    {
        $user = $this->createTestUser('free');
        // 不调用syncPermissionsForTier，用户没有Spatie角色

        $permissions = $this->service->getUserPermissions($user->id);
        $this->assertEquals([], $permissions);
    }
}
