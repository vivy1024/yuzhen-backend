<?php

namespace App\Modules\SocialLogin\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\SocialLogin\Services\SocialLoginService;
use Illuminate\Http\JsonResponse;

/**
 * Social Account Controller
 * 
 * 社交账号管理控制器
 */
class SocialAccountController extends BaseController
{
    protected SocialLoginService $socialLoginService;
    
    public function __construct(SocialLoginService $socialLoginService)
    {
        $this->socialLoginService = $socialLoginService;
    }

    /**
     * 获取用户已绑定的社交账号列表
     */
    public function index(): JsonResponse
    {
        try {
            $userId = auth()->id();
            $accounts = $this->socialLoginService->getUserAccounts($userId);
            
            return $this->success([
                'accounts' => $accounts,
                'count' => count($accounts),
            ], '获取社交账号列表成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取社交账号列表');
        }
    }
}

