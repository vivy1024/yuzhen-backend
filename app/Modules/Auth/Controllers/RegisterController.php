<?php

namespace App\Modules\Auth\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\EmailService;
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
    protected EmailService $emailService;
    
    public function __construct(AuthService $authService, EmailService $emailService)
    {
        $this->authService = $authService;
        $this->emailService = $emailService;
    }

    /**
     * 用户注册
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // 验证邮箱验证码
            $verifyResult = $this->emailService->verifyCode($data['email'], $data['email_code']);
            if (!$verifyResult['success']) {
                return $this->fail($verifyResult['message'], 422);
            }
            
            // 移除验证码字段，不传给AuthService
            unset($data['email_code']);
            
            $result = $this->authService->register($data);
            
            return $this->success($result, '注册成功', 201);
            
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }
}

