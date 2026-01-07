<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AdminFeedbackController extends Controller
{
    /**
     * 获取所有反馈列表（管理员）
     */
    public function index(Request $request): JsonResponse
    {
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

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => $feedbacks->items(),
            'meta' => [
                'current_page' => $feedbacks->currentPage(),
                'last_page' => $feedbacks->lastPage(),
                'per_page' => $feedbacks->perPage(),
                'total' => $feedbacks->total(),
            ]
        ]);
    }

    /**
     * 获取反馈统计
     */
    public function stats(): JsonResponse
    {
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

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => $stats
        ]);
    }

    /**
     * 获取单个反馈详情（管理员）
     */
    public function show(int $id): JsonResponse
    {
        $feedback = Feedback::with(['user:id,nickname,email,avatar', 'replier:id,nickname'])
            ->find($id);

        if (!$feedback) {
            return response()->json([
                'code' => 404,
                'message' => '反馈不存在'
            ], 404);
        }

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => $feedback
        ]);
    }

    /**
     * 回复反馈（管理员）
     */
    public function reply(Request $request, int $id): JsonResponse
    {
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
            return response()->json([
                'code' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $feedback = Feedback::find($id);

        if (!$feedback) {
            return response()->json([
                'code' => 404,
                'message' => '反馈不存在'
            ], 404);
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

        return response()->json([
            'code' => 200,
            'message' => '回复成功',
            'data' => $feedback
        ]);
    }

    /**
     * 更新反馈状态（管理员）
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,processing,resolved,closed',
        ], [
            'status.required' => '请选择状态',
            'status.in' => '状态无效',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $feedback = Feedback::find($id);

        if (!$feedback) {
            return response()->json([
                'code' => 404,
                'message' => '反馈不存在'
            ], 404);
        }

        $feedback->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'code' => 200,
            'message' => '状态更新成功',
            'data' => $feedback
        ]);
    }

    /**
     * 批量更新状态（管理员）
     */
    public function batchUpdateStatus(Request $request): JsonResponse
    {
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
            return response()->json([
                'code' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $count = Feedback::whereIn('id', $request->feedback_ids)
            ->update(['status' => $request->status]);

        return response()->json([
            'code' => 200,
            'message' => "成功更新 {$count} 条反馈状态",
            'data' => ['updated_count' => $count]
        ]);
    }

    /**
     * 删除反馈（管理员）
     */
    public function destroy(int $id): JsonResponse
    {
        $feedback = Feedback::find($id);

        if (!$feedback) {
            return response()->json([
                'code' => 404,
                'message' => '反馈不存在'
            ], 404);
        }

        $feedback->delete();

        return response()->json([
            'code' => 200,
            'message' => '删除成功'
        ]);
    }
}
