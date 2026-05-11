<?php

namespace App\Modules\Credits\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Credits\Services\InviteService;
use Illuminate\Http\JsonResponse;

/**
 * InviteController - 邀请好友控制器
 *
 * API 端点：
 * - GET /api/credits/v2/invite/code — 获取我的邀请码
 * - GET /api/credits/v2/invite/stats — 邀请统计
 *
 * 所有接口需要 jwt.auth 中间件
 */
class InviteController extends BaseController
{
    private InviteService $inviteService;

    public function __construct(InviteService $inviteService)
    {
        $this->inviteService = $inviteService;
    }

    /**
     * 获取我的邀请码
     * GET /api/credits/v2/invite/code
     */
    public function code(): JsonResponse
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return $this->fail('用户未登录', 401);
            }

            $inviteCode = $this->inviteService->getUserInviteCode($userId);

            return $this->success([
                'invite_code' => $inviteCode,
            ], '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取邀请码');
        }
    }

    /**
     * 邀请统计
     * GET /api/credits/v2/invite/stats
     */
    public function stats(): JsonResponse
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return $this->fail('用户未登录', 401);
            }

            $stats = $this->inviteService->getInviteStats($userId);

            return $this->success($stats, '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取邀请统计');
        }
    }
}
