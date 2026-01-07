<?php

namespace App\Modules\Auth\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Requests\RegisterRequest;
use Illuminate\Http\JsonResponse;

/**
 * Register Controller
 * 
 * 注册控制器
 */
class RegisterController extends BaseController
{
    protected AuthService $authService;
    
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * 用户注册
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $result = $this->authService->register($data);
            
            return $this->success($result, '注册成功', 201);
            
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }
}

