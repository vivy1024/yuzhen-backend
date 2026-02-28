<?php

namespace App\Modules\Auth\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Auth\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

/**
 * 手机绑定控制器
 *
 * 提供手机号绑定、解绑、更换功能
 */
class PhoneBindingController extends BaseController
{
    private SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * 获取手机号绑定状态
     *
     * GET /api/user/phone/status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'has_phone' => !empty($user->phone),
            'phone' => $user->phone ? $this->maskPhone($user->phone) : null,
            'phone_verified' => !empty($user->phone_verified_at),
            'phone_bound_at' => $user->phone_bound_at?->toDateTimeString(),
        ]);
    }

    /**
     * 绑定手机号
     *
     * POST /api/user/phone/bind
     */
    public function bind(Request $request): JsonResponse
    {
        $user = $request->user();

        // 已绑定检查
        if ($user->phone && $user->phone_verified_at) {
            return $this->fail('您已绑定手机号，如需更换请使用更换功能', 422);
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^1[3-9]\d{9}$/',
            'code' => 'required|string|size:6',
        ], [
            'phone.required' => '请输入手机号',
            'phone.regex' => '手机号格式不正确',
            'code.required' => '请输入验证码',
            'code.size' => '验证码必须是6位',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 检查手机号是否已被其他用户绑定
        $existingUser = \App\Models\User::where('phone', $request->phone)
            ->where('id', '!=', $user->id)
            ->first();

        if ($existingUser) {
            return $this->fail('该手机号已被其他账号绑定', 422);
        }

        // 验证验证码
        $result = $this->smsService->verifyCode($request->phone, $request->code);
        if (!$result['success']) {
            return $this->fail($result['message'], 422);
        }

        // 绑定手机号
        $user->update([
            'phone' => $request->phone,
            'phone_verified_at' => now(),
            'phone_bound_at' => now(),
        ]);

        return $this->success([
            'phone' => $this->maskPhone($user->phone),
            'phone_verified' => true,
        ], '手机号绑定成功');
    }

    /**
     * 解绑手机号
     *
     * POST /api/user/phone/unbind
     */
    public function unbind(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->phone) {
            return $this->fail('未绑定手机号', 422);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ], [
            'password.required' => '请输入密码确认解绑',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 密码验证
        if (!Hash::check($request->password, $user->password)) {
            return $this->fail('密码错误', 422);
        }

        // 检查是否有其他登录方式
        // 如果邮箱是占位符，说明没有真实邮箱
        $hasRealEmail = $user->email
            && !str_contains($user->email, '@placeholder.local')
            && !str_contains($user->email, 'phone_');

        if (!$hasRealEmail) {
            return $this->fail('请先绑定邮箱后再解绑手机号，否则您将无法登录', 422);
        }

        // 解绑手机号
        $user->update([
            'phone' => null,
            'phone_verified_at' => null,
            'phone_bound_at' => null,
        ]);

        return $this->success(null, '手机号已解绑');
    }

    /**
     * 更换手机号
     *
     * POST /api/user/phone/change
     */
    public function change(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'new_phone' => 'required|string|regex:/^1[3-9]\d{9}$/',
            'new_code' => 'required|string|size:6',
            'old_code' => 'nullable|string|size:6',
        ], [
            'new_phone.required' => '请输入新手机号',
            'new_phone.regex' => '新手机号格式不正确',
            'new_code.required' => '请输入新手机验证码',
            'new_code.size' => '验证码必须是6位',
            'old_code.size' => '验证码必须是6位',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 检查新手机号是否已被其他用户绑定
        $existingUser = \App\Models\User::where('phone', $request->new_phone)
            ->where('id', '!=', $user->id)
            ->first();

        if ($existingUser) {
            return $this->fail('该手机号已被其他账号绑定', 422);
        }

        // 如果已绑定手机号，需要验证原手机号
        if ($user->phone && $user->phone_verified_at) {
            if (!$request->old_code) {
                return $this->fail('请输入原手机号验证码', 422);
            }

            $oldResult = $this->smsService->verifyCode($user->phone, $request->old_code);
            if (!$oldResult['success']) {
                return $this->fail('原手机号验证失败: ' . $oldResult['message'], 422);
            }
        }

        // 验证新手机号
        $newResult = $this->smsService->verifyCode($request->new_phone, $request->new_code);
        if (!$newResult['success']) {
            return $this->fail('新手机号验证失败: ' . $newResult['message'], 422);
        }

        // 更新手机号
        $user->update([
            'phone' => $request->new_phone,
            'phone_verified_at' => now(),
            'phone_bound_at' => now(),
        ]);

        return $this->success([
            'phone' => $this->maskPhone($user->phone),
        ], '手机号更换成功');
    }

    /**
     * 发送绑定验证码
     *
     * POST /api/user/phone/send-bind-code
     */
    public function sendBindCode(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^1[3-9]\d{9}$/',
        ], [
            'phone.required' => '请输入手机号',
            'phone.regex' => '手机号格式不正确',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 检查手机号是否已被绑定
        $existingUser = \App\Models\User::where('phone', $request->phone)
            ->where('id', '!=', $user->id)
            ->first();

        if ($existingUser) {
            return $this->fail('该手机号已被其他账号绑定', 422);
        }

        // 发送验证码
        $result = $this->smsService->sendVerificationCode($request->phone, $request->ip());

        if (!$result['success']) {
            return $this->fail($result['message'], 422);
        }

        return $this->success([
            'expires_at' => $result['expires_at'] ?? null,
        ], '验证码已发送');
    }

    /**
     * 手机号脱敏
     */
    private function maskPhone(string $phone): string
    {
        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    }
}