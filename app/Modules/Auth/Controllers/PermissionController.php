<?php

namespace App\Modules\Auth\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Services\PermissionService;
use App\Services\MembershipService;
use App\Modules\Auth\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * 权限控制器
 *
 * 提供权限查询和Token强制刷新功能
 */
class PermissionController extends BaseController
{
    public function __construct(
        private PermissionService $permissionService,
        private MembershipService $membershipService,
        private JwtService $jwtService
    ) {}

    /**
     * 获取当前用户权限
     *
     * GET /api/auth/permissions
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tier = $this->membershipService->getUserTier($user->id);
        $permissions = $this->permissionService->getUserPermissions($user->id);
        $membership = $this->membershipService->getCurrentMembership($user->id);

        return $this->success([
            'tier' => $tier,
            'permissions' => $permissions,
            'membership' => $membership,
            'user_id' => $user->id,
        ]);
    }

    /**
     * 强制刷新Token（权限变更后调用）
     *
     * POST /api/auth/permissions/refresh-token
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();

        // 生成包含最新权限的新Token
        $newToken = $this->jwtService->generateToken($user);
        $newRefreshToken = $this->jwtService->generateRefreshToken($user);

        return $this->success([
            'access_token' => $newToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => config('auth.jwt_ttl', 3600),
        ], 'Token已刷新，权限已更新');
    }

    /**
     * 检查权限并建议刷新
     *
     * GET /api/auth/permissions/check
     */
    public function check(Request $request): JsonResponse
    {
        $user = $request->user();

        // 从当前JWT中解析的权限（由中间件设置）
        $jwtPayload = $request->attributes->get('jwt_payload', []);

        // 获取数据库中的最新权限
        $currentTier = $this->membershipService->getUserTier($user->id);
        $currentPermissions = $this->permissionService->getUserPermissions($user->id);

        // 比较是否一致
        $jwtTier = $jwtPayload['tier'] ?? null;
        $jwtPermissions = $jwtPayload['permissions'] ?? [];

        $tierMismatch = $jwtTier !== $currentTier;
        $permissionsMismatch = !empty(array_diff($currentPermissions, $jwtPermissions)) ||
                              !empty(array_diff($jwtPermissions, $currentPermissions));

        return $this->success([
            'jwt_tier' => $jwtTier,
            'current_tier' => $currentTier,
            'jwt_permissions' => $jwtPermissions,
            'current_permissions' => $currentPermissions,
            'needs_refresh' => $tierMismatch || $permissionsMismatch,
            'tier_mismatch' => $tierMismatch,
            'permissions_mismatch' => $permissionsMismatch,
        ]);
    }
}