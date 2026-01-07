<?php

namespace App\Modules\Admin\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理员用户控制器
 * 
 * 用户列表、会员状态管理
 */
class AdminUserController extends BaseController
{
    /**
     * 获取用户列表
     * 
     * GET /api/admin/users
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::with(['membership.membership']);
            
            // 搜索
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }
            
            // 角色筛选
            if ($request->has('role') && $request->role) {
                $query->where('role', $request->role);
            }
            
            $users = $query->orderBy('created_at', 'desc')->paginate(20);
            
            // 统计
            $stats = [
                'total' => User::count(),
                'members' => User::whereHas('membership', function($q) {
                    $q->where('is_active', true);
                })->count(),
                'admins' => User::where('role', 'admin')->count(),
            ];
            
            return $this->success([
                'users' => $users->items(),
                'stats' => $stats,
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
            ], '获取用户列表成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取用户列表');
        }
    }

    /**
     * 获取用户详情
     * 
     * GET /api/admin/users/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = User::with(['membership.membership', 'profile'])
                ->findOrFail($id);
            
            return $this->success($user, '获取用户详情成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取用户详情');
        }
    }

    /**
     * 更新用户角色
     * 
     * PUT /api/admin/users/{id}/role
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'role' => 'required|in:user,expert,admin',
            ]);
            
            $user = User::findOrFail($id);
            
            // 不能修改自己的角色
            if ($user->id === auth()->id()) {
                return $this->fail('不能修改自己的角色');
            }
            
            $user->role = $request->role;
            $user->save();
            
            return $this->success([
                'id' => $user->id,
                'role' => $user->role,
            ], '角色更新成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '更新用户角色');
        }
    }
}
