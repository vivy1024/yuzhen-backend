<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * 角色权限预置数据 Seeder
 * 
 * 创建三个会员角色和六个权限，并建立映射关系
 * 
 * 角色：free、warmheart、energy
 * 权限：dag:query、dag:template:*、agent:query、profile:read、profile:write、analysis:advanced
 * 
 * @version v1.0.0
 * @date 2026-02-11
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 清除缓存
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'api';

        // 创建权限
        $permissions = [
            'dag:query',
            'dag:template:*',
            'agent:query',
            'profile:read',
            'profile:write',
            'analysis:advanced',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        // 创建角色并分配权限
        $rolePermissions = [
            'free' => ['dag:query', 'profile:read'],
            'warmheart' => ['dag:query', 'dag:template:*', 'profile:read', 'profile:write'],
            'energy' => ['dag:query', 'dag:template:*', 'agent:query', 'profile:read', 'profile:write', 'analysis:advanced'],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
            $role->syncPermissions($perms);
        }
    }
}
