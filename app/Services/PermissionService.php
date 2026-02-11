<?php

namespace App\Services;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

/**
 * PermissionService - 角色权限同步服务
 * 
 * 基于Spatie/laravel-permission管理用户角色和权限
 * 根据会员等级同步Spatie角色，提供权限查询接口
 * 
 * 角色与权限映射：
 * - free: dag:query, profile:read
 * - warmheart: dag:query, dag:template:*, profile:read, profile:write
 * - energy: dag:query, dag:template:*, agent:query, profile:read, profile:write, analysis:advanced
 * 
 * @version v1.0.0
 * @date 2026-02-11
 * @requirements 3.3, 3.4
 */
class PermissionService
{
    /**
     * 有效的会员等级（对应Spatie角色名）
     */
    const VALID_TIERS = ['free', 'warmheart', 'energy'];

    /**
     * 根据会员等级同步用户角色和权限
     * 
     * 移除用户当前所有角色，分配与会员等级对应的Spatie角色
     * 角色关联的权限由RolePermissionSeeder预置
     * 
     * @param User $user 用户模型
     * @param string $tier 会员等级 (free|warmheart|energy)
     * @return void
     */
    public function syncPermissionsForTier(User $user, string $tier): void
    {
        if (!in_array($tier, self::VALID_TIERS)) {
            Log::warning('PermissionService: 无效的会员等级', [
                'user_id' => $user->id,
                'tier' => $tier,
            ]);
            return;
        }

        // syncRoles 会移除旧角色并分配新角色
        $user->syncRoles([$tier]);

        Log::info('PermissionService: 用户角色已同步', [
            'user_id' => $user->id,
            'tier' => $tier,
            'permissions' => $user->getPermissionNames()->toArray(),
        ]);
    }

    /**
     * 获取用户的完整权限列表
     * 
     * @param int $userId 用户ID
     * @return array 权限名称字符串数组
     */
    public function getUserPermissions(int $userId): array
    {
        $user = User::find($userId);

        if (!$user) {
            Log::warning('PermissionService: 用户不存在', [
                'user_id' => $userId,
            ]);
            return [];
        }

        return $user->getAllPermissions()->pluck('name')->toArray();
    }
}
