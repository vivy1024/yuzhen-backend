<?php

namespace App\Modules\Auth\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Login Controller
 * 
 * 登录控制器
 */
class LoginController extends BaseController
{
    protected AuthService $authService;
    
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * 用户登录
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $ip = $request->ip();
            
            $result = $this->authService->login(
                $data['identifier'],
                $data['password'],
                $ip
            );
            
            return $this->success($result, '登录成功');
            
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 401);
        }
    }

    /**
     * 用户登出
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $this->authService->logout($user);
            
            return $this->success(null, '登出成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '登出');
        }
    }

    /**
     * 刷新Token
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $refreshToken = $request->input('refresh_token');
            
            if (!$refreshToken) {
                return $this->fail('缺少刷新令牌', 400);
            }
            
            $result = $this->authService->refreshToken($refreshToken);
            
            return $this->success($result, '刷新令牌成功');
            
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 401);
        }
    }
}

