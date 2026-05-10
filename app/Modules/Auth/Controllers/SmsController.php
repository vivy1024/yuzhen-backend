<?php

namespace App\Modules\Auth\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Auth\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * 短信验证控制器
 * 
 * 处理短信验证码发送、验证、登录等功能
 * 
 * @version 1.1.0 - 2026-01-17: 修复API响应规范合规性
 */
class SmsController extends BaseController
{
    private SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * 发送短信验证码
     * 
     * POST /api/auth/sms/send
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'phone' => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
            ], [
                'phone.required' => '手机号不能为空',
                'phone.regex' => '手机号格式不正确',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 400);
            }

            $phone = $request->input('phone');
            $ip = $request->ip();

            // 发送验证码
            $result = $this->smsService->sendVerificationCode($phone, $ip);

            if (!$result['success']) {
                $statusCode = 429; // Too Many Requests

                if (str_contains($result['message'], '格式')) {
                    $statusCode = 400;
                } elseif (str_contains($result['message'], '上限')) {
                    $statusCode = 429;
                } elseif (str_contains($result['message'], '失败')) {
                    $statusCode = 500;
                }

                return $this->fail($result['message'], $statusCode, [
                    'wait_seconds' => $result['wait_seconds'] ?? null,
                ]);
            }

            return $this->success([
                'expires_at' => $result['expires_at'],
                'wait_seconds' => $result['wait_seconds'],
            ], $result['message']);
            
        } catch (\Exception $e) {
            return $this->handleException($e, '发送短信验证码');
        }
    }

    /**
     * 验证短信验证码
     * 
     * POST /api/auth/sms/verify
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function verify(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'phone' => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
                'code' => ['required', 'string', 'size:6'],
            ], [
                'phone.required' => '手机号不能为空',
                'phone.regex' => '手机号格式不正确',
                'code.required' => '验证码不能为空',
                'code.size' => '验证码必须为6位',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 400);
            }

            $phone = $request->input('phone');
            $code = $request->input('code');

            // 验证验证码
            $result = $this->smsService->verifyCode($phone, $code);

            if (!$result['success']) {
                $statusCode = 400;

                if (str_contains($result['message'], '过期')) {
                    $statusCode = 400;
                } elseif (str_contains($result['message'], '锁定')) {
                    $statusCode = 403;
                }

                return $this->fail($result['message'], $statusCode);
            }

            return $this->success([
                'verified' => true,
            ], $result['message']);
            
        } catch (\Exception $e) {
            return $this->handleException($e, '验证短信验证码');
        }
    }

    /**
     * 手机号验证码登录
     * 
     * POST /api/auth/sms/login
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'phone' => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
                'code' => ['required', 'string', 'size:6'],
            ], [
                'phone.required' => '手机号不能为空',
                'phone.regex' => '手机号格式不正确',
                'code.required' => '验证码不能为空',
                'code.size' => '验证码必须为6位',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 400);
            }

            $phone = $request->input('phone');
            $code = $request->input('code');
            $ip = $request->ip();

            // 手机号登录
            $result = $this->smsService->loginWithSms($phone, $code, $ip);

            if (!$result['success']) {
                $statusCode = 400;

                if (str_contains($result['message'], '未注册')) {
                    $statusCode = 404;
                } elseif (str_contains($result['message'], '禁用')) {
                    $statusCode = 403;
                } elseif (str_contains($result['message'], '锁定')) {
                    $statusCode = 403;
                } elseif (str_contains($result['message'], '验证码')) {
                    $statusCode = 400;
                }

                return $this->fail($result['message'], $statusCode);
            }

            return $this->success($result['data'], $result['message']);
            
        } catch (\Exception $e) {
            return $this->handleException($e, '手机号验证码登录');
        }
    }

    /**
     * 检查手机号是否已注册
     * 
     * GET /api/auth/sms/check-phone?phone=13800138000
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkPhone(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'phone' => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
            ], [
                'phone.required' => '手机号不能为空',
                'phone.regex' => '手机号格式不正确',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 400);
            }

            $phone = $request->input('phone');
            $isRegistered = $this->smsService->isPhoneRegistered($phone);

            return $this->success([
                'phone' => $phone,
                'is_registered' => $isRegistered,
            ], '查询成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '检查手机号');
        }
    }

    /**
     * 手机号重置密码
     * 
     * POST /api/auth/sms/reset-password
     * 参数: phone, code, password, password_confirmation
     */
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone' => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
                'code' => 'required|string|size:6',
                'password' => 'required|string|min:8|confirmed',
            ], [
                'phone.required' => '手机号不能为空',
                'phone.regex' => '手机号格式不正确',
                'code.required' => '验证码不能为空',
                'code.size' => '验证码为6位数字',
                'password.required' => '新密码不能为空',
                'password.min' => '密码至少8位',
                'password.confirmed' => '两次密码不一致',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 422);
            }

            $phone = $request->input('phone');
            $code = $request->input('code');
            $password = $request->input('password');

            // 验证短信验证码
            $verifyResult = $this->smsService->verifyCode($phone, $code);
            if (!$verifyResult['success']) {
                return $this->fail($verifyResult['message'], 422);
            }

            // 查找用户
            $user = \App\Modules\User\Models\User::where('phone', $phone)->first();
            if (!$user) {
                return $this->fail('该手机号未注册', 404);
            }

            // 更新密码
            $user->password = \Illuminate\Support\Facades\Hash::make($password);
            $user->save();

            return $this->success(null, '密码重置成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '手机号重置密码');
        }
    }
}
