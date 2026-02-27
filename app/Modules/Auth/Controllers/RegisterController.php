<?php

namespace App\Modules\Auth\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\EmailService;
use App\Modules\Auth\Services\SmsService;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Requests\PhoneRegisterRequest;
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
    protected SmsService $smsService;

    public function __construct(
        AuthService $authService,
        EmailService $emailService,
        SmsService $smsService
    ) {
        $this->authService = $authService;
        $this->emailService = $emailService;
        $this->smsService = $smsService;
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

    /**
     * 手机号注册
     *
     * @param PhoneRegisterRequest $request
     * @return JsonResponse
     */
    public function registerByPhone(PhoneRegisterRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // 验证手机验证码
            $verifyResult = $this->smsService->verifyCode($data['phone'], $data['phone_code']);
            if (!$verifyResult['success']) {
                return $this->fail($verifyResult['message'], 422);
            }

            // 移除验证码字段
            unset($data['phone_code']);

            // 为纯手机号用户设置邮箱占位符
            // 用户后续可以在设置中绑定真实邮箱
            if (!isset($data['email']) || empty($data['email'])) {
                $data['email'] = 'phone_' . $data['phone'] . '@placeholder.local';
            }

            // nickname 映射到 name 字段
            if (isset($data['nickname']) && !isset($data['name'])) {
                $data['name'] = $data['nickname'];
                unset($data['nickname']);
            }

            $result = $this->authService->register($data);

            return $this->success($result, '注册成功', 201);

        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }
}

