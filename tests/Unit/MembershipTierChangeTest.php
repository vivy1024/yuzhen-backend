<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\MembershipService;
use App\Services\PermissionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * MembershipService::updateUserTier 单元测试
 *
 * 验证会员等级变更时：
 * - 权限缓存立即清除（Requirements 4.5）
 * - 审计日志正确记录（Requirements 8.3）
 * - 返回包含刷新标记（JWT权限实时性）
 */
class MembershipTierChangeTest extends TestCase
{
    use RefreshDatabase;

    private MembershipService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->service = app(MembershipService::class);
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
     * 测试等级变更后权限缓存被清除，新权限立即生效
     * Validates: Requirements 4.5
     */
    public function test_tier_change_updates_permissions_immediately(): void
    {
        $user = $this->createTestUser('free');
        app(PermissionService::class)->syncPermissionsForTier($user, 'free');

        // 升级到energy
        $result = $this->service->updateUserTier($user->id, 'energy');
        $this->assertTrue($result['success']);

        // 验证数据库中membership_tier已更新
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'membership_tier' => 'energy',
        ]);

        // 验证Spatie角色已在数据库中更新为energy
        $energyRole = \Spatie\Permission\Models\Role::where('name', 'energy')->first();
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $user->id,
            'role_id' => $energyRole->id,
        ]);

        // 确认旧的free角色已被移除
        $freeRole = \Spatie\Permission\Models\Role::where('name', 'free')->first();
        $this->assertDatabaseMissing('model_has_roles', [
            'model_id' => $user->id,
            'role_id' => $freeRole->id,
        ]);
    }

    /**
     * 测试等级变更返回刷新标记
     * Validates: JWT权限实时性
     */
    public function test_tier_change_returns_refresh_flag(): void
    {
        $user = $this->createTestUser('free');

        $result = $this->service->updateUserTier($user->id, 'energy');

        $this->assertTrue($result['success']);
        $this->assertEquals('free', $result['old_tier']);
        $this->assertEquals('energy', $result['new_tier']);
        $this->assertTrue($result['token_refresh_required']);
    }

    /**
     * 测试等级变更记录包含完整字段的审计日志
     * Validates: Requirements 8.3
     */
    public function test_tier_change_logs_audit_with_required_fields(): void
    {
        Log::spy();

        $user = $this->createTestUser('free');
        $userId = $user->id;
        $this->service->updateUserTier($userId, 'warmheart');

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) use ($userId) {
                if ($message !== '[AUDIT] 会员等级变更') {
                    return false;
                }
                return $context['audit_type'] === 'membership_tier_change'
                    && $context['user_id'] === $userId
                    && $context['old_tier'] === 'free'
                    && $context['new_tier'] === 'warmheart'
                    && isset($context['changed_at']);
            })
            ->once();
    }

    /**
     * 测试降级时审计日志正确记录旧等级和新等级
     * Validates: Requirements 8.3
     */
    public function test_tier_downgrade_logs_correct_tiers(): void
    {
        Log::spy();

        $user = $this->createTestUser('energy');
        $this->service->updateUserTier($user->id, 'free');

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) {
                if ($message !== '[AUDIT] 会员等级变更') {
                    return false;
                }
                return $context['old_tier'] === 'energy'
                    && $context['new_tier'] === 'free';
            })
            ->once();
    }

    /**
     * 测试无效等级不触发审计日志
     */
    public function test_invalid_tier_does_not_trigger_audit(): void
    {
        Log::spy();

        $user = $this->createTestUser('free');
        $result = $this->service->updateUserTier($user->id, 'invalid');
        $this->assertFalse($result['success']);

        Log::shouldNotHaveReceived('info', function ($message) {
            return $message === '[AUDIT] 会员等级变更';
        });
    }

    /**
     * 测试用户不存在时不触发审计日志
     */
    public function test_nonexistent_user_does_not_trigger_audit(): void
    {
        Log::spy();

        $result = $this->service->updateUserTier(99999, 'energy');
        $this->assertFalse($result['success']);

        Log::shouldNotHaveReceived('info', function ($message) {
            return $message === '[AUDIT] 会员等级变更';
        });
    }
}