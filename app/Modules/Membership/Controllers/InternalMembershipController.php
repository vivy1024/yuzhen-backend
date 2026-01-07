<?php

namespace App\Modules\Membership\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Membership\Services\MembershipService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Internal Membership API Controller
 * 
 * 用于MCP/CrewAI等内部服务检查用户会员权限
 * 
 * 需要 X-Internal-Token 认证
 */
class InternalMembershipController extends BaseController
{
    protected MembershipService $membershipService;

    public function __construct(MembershipService $membershipService)
    {
        $this->membershipService = $membershipService;
    }

    /**
     * 检查用户权限（内部API版本）
     * 
     * POST /api/internal/membership/check-permission
     * 
     * Request Body:
     * {
     *   "user_id": 1,
     *   "feature": "ai_recommendation"
     * }
     * 
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "user_id": 1,
     *     "feature": "ai_recommendation",
     *     "has_permission": true,
     *     "membership_tier": "warmheart",
     *     "permissions": {
     *       "unlock_all_exercises": true,
     *       "ai_recommendation": true,
     *       "data_analysis": true,
     *       "coach_service": false
     *     }
     *   }
     * }
     */
    public function checkPermission(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id');
            $feature = $request->input('feature');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'user_id is required',
                ], 400);
            }

            if (!$feature) {
                return response()->json([
                    'success' => false,
                    'message' => 'feature is required (ai_recommendation, data_analysis, coach_service)',
                ], 400);
            }

            // 检查权限
            $hasPermission = $this->membershipService->checkPermission($userId, $feature);

            // 获取用户会员信息（用于返回完整权限列表）
            $userMembership = $this->membershipService->getUserMembership($userId);
            $membershipTier = 'free';
            $allPermissions = [
                'unlock_all_exercises' => true,  // 免费版默认权限
                'ai_recommendation' => false,
                'data_analysis' => false,
                'coach_service' => false,
            ];

            if ($userMembership && ($userMembership['is_active'] ?? 0) == 1) {
                $membership = $this->membershipService->getMembershipById($userMembership['membership_id']);
                if ($membership) {
                    $membershipTier = $membership['slug'];
                    $allPermissions = [
                        'unlock_all_exercises' => $membership['unlock_all_exercises'] ?? true,
                        'ai_recommendation' => $membership['ai_recommendation'] ?? false,
                        'data_analysis' => $membership['data_analysis'] ?? false,
                        'coach_service' => $membership['coach_service'] ?? false,
                    ];
                }
            }

            Log::info("MCP检查会员权限", [
                'user_id' => $userId,
                'feature' => $feature,
                'has_permission' => $hasPermission,
                'tier' => $membershipTier,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'feature' => $feature,
                    'has_permission' => $hasPermission,
                    'membership_tier' => $membershipTier,
                    'permissions' => $allPermissions,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error("MCP检查权限失败", [
                'user_id' => $request->input('user_id'),
                'feature' => $request->input('feature'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '检查权限失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取用户完整会员信息（内部API版本）
     * 
     * GET /api/internal/membership/user/{userId}
     */
    public function getUserMembership(int $userId): JsonResponse
    {
        try {
            // ✅ 性能优化：getActiveMembership已经包含了membership信息（通过with预加载）
            $userMembership = $this->membershipService->getUserMembership($userId);

            if (!$userMembership) {
                // 🔧 修复：如果 user_memberships 表没有记录，从 users.membership_tier 字段读取
                $user = User::find($userId);
                $tierFromUserTable = $user ? $user->membership_tier : 'free';
                
                // 标准化 tier 值（newbie → free）
                $normalizedTier = ($tierFromUserTable === 'newbie') ? 'free' : $tierFromUserTable;
                
                // 根据 tier 返回对应权限
                $permissions = $this->getPermissionsByTier($normalizedTier);
                
                Log::info("MCP获取会员信息: 使用users表的membership_tier", [
                    'user_id' => $userId,
                    'raw_tier' => $tierFromUserTable,
                    'normalized_tier' => $normalizedTier,
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'tier' => $normalizedTier,
                        'status' => 'active',
                        'permissions' => $permissions,
                    ],
                ]);
            }

            // ✅ 性能优化：直接使用预加载的数据，不需要再次查询membership表
            $tier = $userMembership['tier'] ?? 'free';
            $permissions = $this->getPermissionsByTier($tier);

            return response()->json([
                'success' => true,
                'data' => [
                    'tier' => $tier,
                    'name' => $userMembership['tier_name'] ?? '免费版',
                    'status' => $userMembership['is_active'] ? 'active' : 'inactive',
                    'started_at' => $userMembership['started_at'],
                    'expired_at' => $userMembership['expires_at'],
                    'permissions' => $permissions,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error("MCP获取会员信息失败", [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取会员信息失败: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * 根据会员等级获取权限配置
     * 
     * @param string $tier 会员等级: free, warmheart, energy
     * @return array 权限配置数组
     */
    private function getPermissionsByTier(string $tier): array
    {
        return match($tier) {
            'free' => [
                'unlock_all_exercises' => true,
                'ai_recommendation' => false,
                'data_analysis' => false,
                'coach_service' => false,
            ],
            'warmheart' => [
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => true,
                'coach_service' => false,
            ],
            'energy' => [
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => true,
                'coach_service' => true,
            ],
            default => [
                'unlock_all_exercises' => true,
                'ai_recommendation' => false,
                'data_analysis' => false,
                'coach_service' => false,
            ],
        };
    }
}












