<?php

namespace App\Modules\Membership\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Membership\Services\MembershipService;
use App\Modules\Membership\Resources\MembershipResource;
use App\Modules\Membership\Resources\UserMembershipResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Membership Controller
 * 
 * 会员等级管理控制器
 */
class MembershipController extends BaseController
{
    protected MembershipService $membershipService;

    public function __construct(MembershipService $membershipService)
    {
        $this->membershipService = $membershipService;
    }

    /**
     * 获取所有会员等级
     * 
     * GET /api/membership/tiers
     */
    public function index(): JsonResponse
    {
        try {
            $memberships = $this->membershipService->getAllMemberships();
            
            return $this->success([
                'memberships' => MembershipResource::collection(collect($memberships)),
                'count' => count($memberships),
            ], '获取会员等级成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取会员等级');
        }
    }

    /**
     * 获取当前用户会员信息
     * 
     * GET /api/membership/current
     */
    public function getCurrent(Request $request): JsonResponse
    {
        try {
            // ✅ 获取用户ID并验证
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'code' => 401,
                    'msg' => '用户未认证，请重新登录',
                    'data' => null
                ], 401);
            }
            
            $userMembership = $this->membershipService->getUserMembership($userId);

            if (!$userMembership) {
                return $this->success([
                    'tier' => 'free',
                    'message' => '当前为免费版，升级即可解锁更多功能',
                ], '暂无会员信息');
            }

            return $this->success(
                new UserMembershipResource($userMembership),
                '获取会员信息成功'
            );

        } catch (\Exception $e) {
            return $this->handleException($e, '获取会员信息');
        }
    }

    /**
     * 检查用户权限
     * 
     * POST /api/membership/check-permission
     */
    public function checkPermission(Request $request): JsonResponse
    {
        try {
            // ✅ 获取用户ID并验证
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'code' => 401,
                    'msg' => '用户未认证，请重新登录',
                    'data' => null
                ], 401);
            }
            
            $feature = $request->input('feature');

            $hasPermission = $this->membershipService->checkPermission($userId, $feature);

            return $this->success([
                'feature' => $feature,
                'has_permission' => $hasPermission,
            ], '权限检查完成');

        } catch (\Exception $e) {
            return $this->handleException($e, '权限检查');
        }
    }

    /**
     * 获取会员统计（管理员）
     * 
     * GET /api/membership/stats
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->membershipService->getMembershipStats();

            return $this->success($stats, '获取统计成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取统计');
        }
    }
}

