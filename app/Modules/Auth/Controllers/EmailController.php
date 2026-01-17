<?php

namespace App\Modules\Auth\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Auth\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * 邮箱验证控制器
 * 
 * 处理邮箱验证码发送、验证、登录等功能
 * 
 * @version 1.1.0 - 2026-01-17: 修复API响应规范合规性
 */
class EmailController extends BaseController
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * 发送邮箱验证码
     * 
     * POST /api/auth/email/send
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'email' => ['required', 'string', 'email', 'max:255'],
            ], [
                'email.required' => '邮箱不能为空',
                'email.email' => '邮箱格式不正确',
                'email.max' => '邮箱长度不能超过255个字符',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 400);
            }

            $email = $request->input('email');
            $ip = $request->ip();

            // 发送验证码
            $result = $this->emailService->sendVerificationCode($email, $ip);

            if (!$result['success']) {
                $statusCode = 429; // Too Many Requests

                if (str_contains($result['message'], '格式')) {
                    $statusCode = 400;
                } elseif (str_contains($result['message'], '上限')) {
                    $statusCode = 429;
                } elseif (str_contains($result['message'], '失败')) {
                    $statusCode = 500;
                } elseif (str_contains($result['message'], '锁定')) {
                    $statusCode = 403;
                }

                return $this->fail($result['message'], $statusCode, [
                    'wait_seconds' => $result['wait_seconds'] ?? null,
                ]);
            }

            return $this->success([
                'expires_at' => $result['expires_at'],
            ], $result['message']);
            
        } catch (\Exception $e) {
            return $this->handleException($e, '发送邮箱验证码');
        }
    }

    /**
     * 验证邮箱验证码
     * 
     * POST /api/auth/email/verify
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function verify(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'email' => ['required', 'string', 'email', 'max:255'],
                'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            ], [
                'email.required' => '邮箱不能为空',
                'email.email' => '邮箱格式不正确',
                'code.required' => '验证码不能为空',
                'code.size' => '验证码必须是6位数字',
                'code.regex' => '验证码格式不正确',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 400);
            }

            $email = $request->input('email');
            $code = $request->input('code');

            // 验证验证码
            $result = $this->emailService->verifyCode($email, $code);

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
            return $this->handleException($e, '验证邮箱验证码');
        }
    }

    /**
     * 邮箱验证码登录
     * 
     * POST /api/auth/email/login
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'email' => ['required', 'string', 'email', 'max:255'],
                'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            ], [
                'email.required' => '邮箱不能为空',
                'email.email' => '邮箱格式不正确',
                'code.required' => '验证码不能为空',
                'code.size' => '验证码必须是6位数字',
                'code.regex' => '验证码格式不正确',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 400);
            }

            $email = $request->input('email');
            $code = $request->input('code');

            // 邮箱验证码登录
            $result = $this->emailService->login($email, $code);

            if (!$result['success']) {
                $statusCode = 400;

                if (str_contains($result['message'], '未注册')) {
                    $statusCode = 404;
                } elseif (str_contains($result['message'], '过期')) {
                    $statusCode = 400;
                } elseif (str_contains($result['message'], '锁定')) {
                    $statusCode = 403;
                }

                return $this->fail($result['message'], $statusCode);
            }

            return $this->success([
                'user' => $result['user'],
                'access_token' => $result['tokens']['access_token'],
                'refresh_token' => $result['tokens']['refresh_token'],
                'expires_in' => $result['tokens']['expires_in'],
            ], $result['message']);
            
        } catch (\Exception $e) {
            return $this->handleException($e, '邮箱验证码登录');
        }
    }

    /**
     * 重置密码
     * 
     * POST /api/auth/email/reset-password
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'email' => ['required', 'string', 'email', 'max:255'],
                'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
                'password' => ['required', 'string', 'min:6', 'max:32'],
                'password_confirmation' => ['required', 'same:password'],
            ], [
                'email.required' => '邮箱不能为空',
                'email.email' => '邮箱格式不正确',
                'code.required' => '验证码不能为空',
                'code.size' => '验证码必须是6位数字',
                'code.regex' => '验证码格式不正确',
                'password.required' => '新密码不能为空',
                'password.min' => '密码长度不能少于6位',
                'password.max' => '密码长度不能超过32位',
                'password_confirmation.required' => '确认密码不能为空',
                'password_confirmation.same' => '两次输入的密码不一致',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 400);
            }

            $email = $request->input('email');
            $code = $request->input('code');
            $password = $request->input('password');

            // 重置密码
            $result = $this->emailService->resetPassword($email, $code, $password);

            if (!$result['success']) {
                $statusCode = 400;

                if (str_contains($result['message'], '未注册')) {
                    $statusCode = 404;
                } elseif (str_contains($result['message'], '过期')) {
                    $statusCode = 400;
                } elseif (str_contains($result['message'], '锁定')) {
                    $statusCode = 403;
                }

                return $this->fail($result['message'], $statusCode);
            }

            return $this->success(null, $result['message']);
            
        } catch (\Exception $e) {
            return $this->handleException($e, '重置密码');
        }
    }

    /**
     * 检查邮箱是否已注册
     * 
     * GET /api/auth/email/check
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkEmail(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'email' => ['required', 'string', 'email', 'max:255'],
            ], [
                'email.required' => '邮箱不能为空',
                'email.email' => '邮箱格式不正确',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 400);
            }

            $email = $request->input('email');
            $result = $this->emailService->checkEmailExists($email);

            return $this->success([
                'exists' => $result['exists'],
            ], $result['message']);
            
        } catch (\Exception $e) {
            return $this->handleException($e, '检查邮箱');
        }
    }
}
