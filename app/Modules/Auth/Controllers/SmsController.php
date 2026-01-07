<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * 短信验证控制器
 * 
 * 处理短信验证码发送、验证、登录等功能
 * 
 * @version 1.0.0
 */
class SmsController extends Controller
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
        // 验证请求参数
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
        ], [
            'phone.required' => '手机号不能为空',
            'phone.regex' => '手机号格式不正确',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'code' => 'SMS_INVALID_PHONE',
            ], 400);
        }

        $phone = $request->input('phone');
        $ip = $request->ip();

        // 发送验证码
        $result = $this->smsService->sendVerificationCode($phone, $ip);

        if (!$result['success']) {
            $statusCode = 429; // Too Many Requests
            $errorCode = 'SMS_RATE_LIMITED';

            if (str_contains($result['message'], '格式')) {
                $statusCode = 400;
                $errorCode = 'SMS_INVALID_PHONE';
            } elseif (str_contains($result['message'], '上限')) {
                $errorCode = 'SMS_DAILY_LIMIT';
            } elseif (str_contains($result['message'], '失败')) {
                $statusCode = 500;
                $errorCode = 'SMS_SEND_FAILED';
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'code' => $errorCode,
                'data' => [
                    'wait_seconds' => $result['wait_seconds'],
                ],
            ], $statusCode);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'expires_at' => $result['expires_at'],
                'wait_seconds' => $result['wait_seconds'],
            ],
        ]);
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
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'code' => 'SMS_INVALID_PARAMS',
            ], 400);
        }

        $phone = $request->input('phone');
        $code = $request->input('code');

        // 验证验证码
        $result = $this->smsService->verifyCode($phone, $code);

        if (!$result['success']) {
            $statusCode = 400;
            $errorCode = 'SMS_CODE_INVALID';

            if (str_contains($result['message'], '过期')) {
                $errorCode = 'SMS_CODE_EXPIRED';
            } elseif (str_contains($result['message'], '锁定')) {
                $statusCode = 403;
                $errorCode = 'SMS_PHONE_LOCKED';
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'code' => $errorCode,
            ], $statusCode);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'verified' => true,
            ],
        ]);
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
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'code' => 'SMS_INVALID_PARAMS',
            ], 400);
        }

        $phone = $request->input('phone');
        $code = $request->input('code');
        $ip = $request->ip();

        // 手机号登录
        $result = $this->smsService->loginWithSms($phone, $code, $ip);

        if (!$result['success']) {
            $statusCode = 400;
            $errorCode = 'SMS_LOGIN_FAILED';

            if (str_contains($result['message'], '未注册')) {
                $statusCode = 404;
                $errorCode = 'SMS_PHONE_NOT_FOUND';
            } elseif (str_contains($result['message'], '禁用')) {
                $statusCode = 403;
                $errorCode = 'USER_DISABLED';
            } elseif (str_contains($result['message'], '锁定')) {
                $statusCode = 403;
                $errorCode = 'SMS_PHONE_LOCKED';
            } elseif (str_contains($result['message'], '验证码')) {
                $errorCode = 'SMS_CODE_INVALID';
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'code' => $errorCode,
            ], $statusCode);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['data'],
        ]);
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
        // 验证请求参数
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
        ], [
            'phone.required' => '手机号不能为空',
            'phone.regex' => '手机号格式不正确',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'code' => 'SMS_INVALID_PHONE',
            ], 400);
        }

        $phone = $request->input('phone');
        $isRegistered = $this->smsService->isPhoneRegistered($phone);

        return response()->json([
            'success' => true,
            'data' => [
                'phone' => $phone,
                'is_registered' => $isRegistered,
            ],
        ]);
    }
}
