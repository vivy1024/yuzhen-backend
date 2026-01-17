<?php

namespace App\Modules\Feedback\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * 反馈控制器
 * 
 * @version 1.1.0 - 2026-01-17: 修复API响应规范合规性
 */
class FeedbackController extends BaseController
{
    /**
     * 获取用户的反馈列表
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $feedbacks = Feedback::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->success($feedbacks, '获取反馈列表成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取反馈列表');
        }
    }

    /**
     * 提交反馈
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:feature,bug,question,other',
                'content' => 'required|string|max:2000',
                'images' => 'nullable|array|max:3',
                'images.*' => 'nullable|string',
                'contact' => 'nullable|string|max:100',
            ], [
                'type.required' => '请选择反馈类型',
                'type.in' => '反馈类型无效',
                'content.required' => '请输入反馈内容',
                'content.max' => '反馈内容不能超过2000字',
                'images.max' => '最多上传3张截图',
                'contact.max' => '联系方式不能超过100字',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 422);
            }

            $user = Auth::user();

            // 处理图片（如果是Base64，保存到存储）
            $images = [];
            if ($request->has('images') && is_array($request->images)) {
                foreach ($request->images as $index => $image) {
                    if (str_starts_with($image, 'data:image')) {
                        // Base64图片，保存到存储
                        $savedPath = $this->saveBase64Image($image, $user->id, $index);
                        if ($savedPath) {
                            $images[] = $savedPath;
                        }
                    } else {
                        // 已经是URL
                        $images[] = $image;
                    }
                }
            }

            $feedback = Feedback::create([
                'user_id' => $user->id,
                'type' => $request->type,
                'content' => $request->content,
                'images' => $images,
                'contact' => $request->contact,
                'status' => Feedback::STATUS_PENDING,
            ]);

            return $this->success($feedback, '反馈提交成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '提交反馈');
        }
    }

    /**
     * 获取单个反馈详情
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $feedback = Feedback::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$feedback) {
                return $this->fail('反馈不存在', 404);
            }

            return $this->success($feedback, '获取反馈详情成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取反馈详情');
        }
    }

    /**
     * 上传截图
     */
    public function uploadImage(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|image|max:5120', // 最大5MB
            ], [
                'file.required' => '请选择图片',
                'file.image' => '请上传有效的图片文件',
                'file.max' => '图片大小不能超过5MB',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 422);
            }

            $user = Auth::user();
            $file = $request->file('file');
            
            // 生成文件名
            $filename = 'feedback_' . $user->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            // 保存文件
            $path = $file->storeAs('feedbacks/' . date('Y/m'), $filename, 'public');

            return $this->success([
                'url' => Storage::url($path)
            ], '上传成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '上传截图');
        }
    }

    /**
     * 保存Base64图片
     */
    private function saveBase64Image(string $base64, int $userId, int $index): ?string
    {
        try {
            // 解析Base64
            if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
                $extension = $matches[1];
                $data = substr($base64, strpos($base64, ',') + 1);
                $data = base64_decode($data);
                
                if ($data === false) {
                    return null;
                }

                // 生成文件名
                $filename = 'feedback_' . $userId . '_' . time() . '_' . $index . '.' . $extension;
                $path = 'feedbacks/' . date('Y/m') . '/' . $filename;
                
                // 保存文件
                Storage::disk('public')->put($path, $data);
                
                return Storage::url($path);
            }
        } catch (\Exception $e) {
            \Log::error('保存反馈图片失败: ' . $e->getMessage());
        }
        
        return null;
    }
}
