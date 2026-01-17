<?php

namespace App\Modules\Admin\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * 管理员反馈控制器
 * 
 * @version v1.1.0
 * @date 2026-01-17 (修复API响应规范合规性)
 */
class AdminFeedbackController extends BaseController
{
    /**
     * 获取所有反馈列表（管理员）
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Feedback::with(['user:id,nickname,email,avatar']);

            // 状态筛选
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // 类型筛选
            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            // 搜索
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('content', 'like', "%{$search}%")
                      ->orWhere('contact', 'like', "%{$search}%");
                });
            }

            // 分页
            $perPage = $request->get('per_page', 20);
            $feedbacks = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return $this->page(
                $feedbacks->items(),
                $feedbacks->total(),
                $feedbacks->currentPage(),
                $feedbacks->perPage(),
                'success'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取反馈列表');
        }
    }

    /**
     * 获取反馈统计
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total' => Feedback::count(),
                'pending' => Feedback::where('status', Feedback::STATUS_PENDING)->count(),
                'processing' => Feedback::where('status', Feedback::STATUS_PROCESSING)->count(),
                'resolved' => Feedback::where('status', Feedback::STATUS_RESOLVED)->count(),
                'closed' => Feedback::where('status', Feedback::STATUS_CLOSED)->count(),
                'today' => Feedback::whereDate('created_at', today())->count(),
                'by_type' => [
                    'feature' => Feedback::where('type', Feedback::TYPE_FEATURE)->count(),
                    'bug' => Feedback::where('type', Feedback::TYPE_BUG)->count(),
                    'question' => Feedback::where('type', Feedback::TYPE_QUESTION)->count(),
                    'other' => Feedback::where('type', Feedback::TYPE_OTHER)->count(),
                ]
            ];

            return $this->success($stats, 'success');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取反馈统计');
        }
    }

    /**
     * 获取单个反馈详情（管理员）
     */
    public function show(int $id): JsonResponse
    {
        try {
            $feedback = Feedback::with(['user:id,nickname,email,avatar', 'replier:id,nickname'])
                ->find($id);

            if (!$feedback) {
                return $this->fail('反馈不存在', 404);
            }

            return $this->success($feedback, 'success');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取反馈详情');
        }
    }

    /**
     * 回复反馈（管理员）
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,processing,resolved,closed',
                'reply' => 'required|string|max:2000',
            ], [
                'status.required' => '请选择状态',
                'status.in' => '状态无效',
                'reply.required' => '请输入回复内容',
                'reply.max' => '回复内容不能超过2000字',
            ]);

            if ($validator->fails()) {
                return $this->fail('验证失败', 422, ['errors' => $validator->errors()]);
            }

            $feedback = Feedback::find($id);

            if (!$feedback) {
                return $this->fail('反馈不存在', 404);
            }

            $admin = Auth::user();

            $feedback->update([
                'status' => $request->status,
                'reply' => $request->reply,
                'reply_at' => now(),
                'reply_by' => $admin->id,
            ]);

            // 重新加载关联
            $feedback->load(['user:id,nickname,email,avatar', 'replier:id,nickname']);

            return $this->success($feedback, '回复成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '回复反馈');
        }
    }

    /**
     * 更新反馈状态（管理员）
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,processing,resolved,closed',
            ], [
                'status.required' => '请选择状态',
                'status.in' => '状态无效',
            ]);

            if ($validator->fails()) {
                return $this->fail('验证失败', 422, ['errors' => $validator->errors()]);
            }

            $feedback = Feedback::find($id);

            if (!$feedback) {
                return $this->fail('反馈不存在', 404);
            }

            $feedback->update([
                'status' => $request->status,
            ]);

            return $this->success($feedback, '状态更新成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '更新反馈状态');
        }
    }

    /**
     * 批量更新状态（管理员）
     */
    public function batchUpdateStatus(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'feedback_ids' => 'required|array|min:1',
                'feedback_ids.*' => 'integer|exists:feedbacks,id',
                'status' => 'required|in:pending,processing,resolved,closed',
            ], [
                'feedback_ids.required' => '请选择反馈',
                'feedback_ids.array' => '反馈ID格式错误',
                'status.required' => '请选择状态',
                'status.in' => '状态无效',
            ]);

            if ($validator->fails()) {
                return $this->fail('验证失败', 422, ['errors' => $validator->errors()]);
            }

            $count = Feedback::whereIn('id', $request->feedback_ids)
                ->update(['status' => $request->status]);

            return $this->success(['updated_count' => $count], "成功更新 {$count} 条反馈状态");
            
        } catch (\Exception $e) {
            return $this->handleException($e, '批量更新反馈状态');
        }
    }

    /**
     * 删除反馈（管理员）
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $feedback = Feedback::find($id);

            if (!$feedback) {
                return $this->fail('反馈不存在', 404);
            }

            $feedback->delete();

            return $this->success(null, '删除成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '删除反馈');
        }
    }
}
