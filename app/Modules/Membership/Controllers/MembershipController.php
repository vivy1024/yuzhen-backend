<?php

namespace App\Modules\Membership\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Membership\Services\MembershipService;
use App\Modules\Membership\Services\MembershipConfigService;
use App\Modules\Membership\Resources\MembershipResource;
use App\Modules\Membership\Resources\UserMembershipResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Membership Controller
 * 
 * 会员等级管理控制器
 * 
 * @updated 2026-01-09 添加会员系统配置开关支持
 */
class MembershipController extends BaseController
{
    protected MembershipService $membershipService;
    protected MembershipConfigService $configService;

    public function __construct(
        MembershipService $membershipService,
        MembershipConfigService $configService
    ) {
        $this->membershipService = $membershipService;
        $this->configService = $configService;
    }

    /**
     * 获取会员系统配置（前端用于控制UI显示）
     * 
     * GET /api/membership/config
     * 
     * 无需认证，所有用户都可以获取
     */
    public function getConfig(): JsonResponse
    {
        try {
            $config = $this->configService->getFrontendConfig();
            
            return $this->success($config, '获取配置成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取配置');
        }
    }

    /**
     * 获取所有会员等级
     * 
     * GET /api/membership/tiers
     * 
     * 如果会员系统禁用，返回空列表和提示信息
     */
    public function index(): JsonResponse
    {
        try {
            // 检查会员系统是否启用
            if (!$this->configService->isEnabled()) {
                return $this->success([
                    'memberships' => [],
                    'count' => 0,
                    'system_enabled' => false,
                    'message' => '会员系统暂未开放，当前为免费体验模式',
                ], '会员系统暂未开放');
            }
            
            $memberships = $this->membershipService->getAllMemberships();
            
            return $this->success([
                'memberships' => MembershipResource::collection(collect($memberships)),
                'count' => count($memberships),
                'system_enabled' => true,
            ], '获取会员等级成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取会员等级');
        }
    }

    /**
     * 获取当前用户会员信息
     * 
     * GET /api/membership/current
     * 
     * 如果会员系统禁用，返回统一限制信息
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
            
            // 检查会员系统是否启用
            if (!$this->configService->isEnabled()) {
                $limits = $this->configService->getUnifiedLimits();
                return $this->success([
                    'tier' => 'unified',
                    'tier_name' => '体验用户',
                    'system_enabled' => false,
                    'limits' => $limits,
                    'message' => '当前为免费体验模式，所有用户享受统一服务',
                ], '获取用户限制成功');
            }
            
            $userMembership = $this->membershipService->getUserMembership($userId);

            if (!$userMembership) {
                return $this->success([
                    'tier' => 'free',
                    'system_enabled' => true,
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

