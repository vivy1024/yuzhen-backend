<?php

namespace App\Modules\Membership\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Modules\Membership\Services\MembershipService;
use App\Infrastructure\Http\Responses\ApiResponse;

/**
 * Check Membership Permission Middleware
 * 
 * 检查会员权限中间件
 */
class CheckMembershipPermission
{
    protected MembershipService $membershipService;

    public function __construct(MembershipService $membershipService)
    {
        $this->membershipService = $membershipService;
    }

    public function handle(Request $request, Closure $next, string $feature)
    {
        $userId = auth()->id();

        if (!$userId) {
            return ApiResponse::unauthorized('请先登录');
        }

        $hasPermission = $this->membershipService->checkPermission($userId, $feature);

        if (!$hasPermission) {
            return ApiResponse::forbidden('此功能需要升级会员');
        }

        return $next($request);
    }
}

