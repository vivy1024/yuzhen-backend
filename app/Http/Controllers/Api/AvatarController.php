<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AvatarController extends BaseController
{
    /**
     * 上传头像
     * POST /api/users/avatar
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            ], [
                'avatar.required' => '请选择头像图片',
                'avatar.image' => '文件必须是图片',
                'avatar.mimes' => '仅支持 jpg、png、webp 格式',
                'avatar.max' => '图片大小不能超过 2MB',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败: ' . $validator->errors()->first(), 422);
            }

            $file = $request->file('avatar');
            // SEC-7: 使用 MIME 检测扩展名，防止客户端伪造
            $extension = $file->guessExtension() ?: 'jpg';
            $filename = $user->id . '.' . $extension;

            // 删除旧头像
            if ($user->avatar) {
                Storage::disk('public')->delete('avatars/' . basename($user->avatar));
            }

            // 存储新头像
            $file->storeAs('avatars', $filename, 'public');

            // 更新用户记录
            $avatarUrl = '/storage/avatars/' . $filename;
            $user->update(['avatar' => $avatarUrl]);

            return $this->success([
                'avatar_url' => $avatarUrl,
            ], '头像上传成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '上传头像');
        }
    }
}
