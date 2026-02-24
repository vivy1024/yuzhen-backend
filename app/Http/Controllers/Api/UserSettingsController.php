<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\User\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserSettingsController extends BaseController
{
    /**
     * 获取用户设置
     * GET /api/settings
     */
    public function getSettings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $profile = UserProfile::where('user_id', $user->id)->first();

            $defaults = [
                'theme' => 'system',
                'language' => 'zh-CN',
                'notification_enabled' => true,
                'reminder_time' => '08:00',
            ];

            $preferences = $profile?->preferences ?? [];

            return $this->success(array_merge($defaults, $preferences), '获取设置成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取用户设置');
        }
    }

    /**
     * 更新用户设置
     * PUT /api/settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'theme' => 'sometimes|in:light,dark,system',
                'language' => 'sometimes|string|max:10',
                'notification_enabled' => 'sometimes|boolean',
                'reminder_time' => 'sometimes|date_format:H:i',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败: ' . $validator->errors()->first(), 422);
            }

            $profile = UserProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['basic_info' => [], 'fitness_goals' => []]
            );

            $current = $profile->preferences ?? [];
            $profile->preferences = array_merge($current, $validator->validated());
            $profile->save();

            return $this->success($profile->preferences, '设置更新成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '更新用户设置');
        }
    }

    /**
     * 修改密码
     * POST /api/user/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ], [
                'new_password.confirmed' => '两次输入的新密码不一致',
                'new_password.min' => '新密码至少6个字符',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败: ' . $validator->errors()->first(), 422);
            }

            if (!Hash::check($request->current_password, $user->password)) {
                return $this->fail('当前密码不正确', 422);
            }

            $user->update(['password' => Hash::make($request->new_password)]);

            return $this->success(null, '密码修改成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '修改密码');
        }
    }

    /**
     * 注销账号
     * DELETE /api/user/account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->fail('请输入密码确认注销', 422);
            }

            if (!Hash::check($request->password, $user->password)) {
                return $this->fail('密码不正确', 422);
            }

            // 撤销所有 token
            $user->tokens()->delete();

            // 软删除用户
            $user->delete();

            return $this->success(null, '账号已注销');
        } catch (\Exception $e) {
            return $this->handleException($e, '注销账号');
        }
    }

    /**
     * 获取版本信息
     * GET /api/version
     */
    public function getVersion(): JsonResponse
    {
        return $this->success([
            'version' => config('app.version', '1.4.0'),
            'api_version' => '2.0.0',
        ], '获取版本信息成功');
    }
}
