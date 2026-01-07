<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * 用户投诉控制器
 * 
 * 处理用户投诉的提交、查询和管理
 * Requirements: 16.5
 */
class ComplaintController extends Controller
{
    /**
     * 提交投诉
     * 
     * POST /api/v2/complaints
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(Complaint::getTypes()))],
            'content' => 'required|string|min:10|max:2000',
            'chat_session_id' => 'nullable|exists:chat_sessions,id',
            'screenshot_url' => 'nullable|url|max:500',
        ]);

        try {
            $complaint = Complaint::create([
                'user_id' => Auth::id(),
                'type' => $validated['type'],
                'content' => $validated['content'],
                'chat_session_id' => $validated['chat_session_id'] ?? null,
                'screenshot_url' => $validated['screenshot_url'] ?? null,
                'status' => Complaint::STATUS_PENDING,
            ]);

            // 记录日志
            Log::info('用户投诉已提交', [
                'complaint_id' => $complaint->id,
                'user_id' => Auth::id(),
                'type' => $validated['type'],
            ]);

            return response()->json([
                'success' => true,
                'message' => '投诉已提交，我们将在48小时内处理',
                'data' => [
                    'id' => $complaint->id,
                    'status' => $complaint->status,
                    'status_label' => $complaint->status_label,
                    'created_at' => $complaint->created_at->toIso8601String(),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('投诉提交失败', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '投诉提交失败，请稍后重试',
            ], 500);
        }
    }

    /**
     * 获取用户的投诉列表
     * 
     * GET /api/v2/complaints
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $status = $request->input('status');

        $query = Complaint::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $complaints = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $complaints->map(function ($complaint) {
                return [
                    'id' => $complaint->id,
                    'type' => $complaint->type,
                    'type_label' => $complaint->type_label,
                    'content' => $complaint->content,
                    'status' => $complaint->status,
                    'status_label' => $complaint->status_label,
                    'handler_response' => $complaint->handler_response,
                    'created_at' => $complaint->created_at->toIso8601String(),
                    'handled_at' => $complaint->handled_at?->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $complaints->currentPage(),
                'last_page' => $complaints->lastPage(),
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
            ],
        ]);
    }

    /**
     * 获取投诉详情
     * 
     * GET /api/v2/complaints/{id}
     */
    public function show(int $id): JsonResponse
    {
        $complaint = Complaint::where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $complaint->id,
                'type' => $complaint->type,
                'type_label' => $complaint->type_label,
                'content' => $complaint->content,
                'screenshot_url' => $complaint->screenshot_url,
                'status' => $complaint->status,
                'status_label' => $complaint->status_label,
                'handler_response' => $complaint->handler_response,
                'created_at' => $complaint->created_at->toIso8601String(),
                'handled_at' => $complaint->handled_at?->toIso8601String(),
                'chat_session_id' => $complaint->chat_session_id,
            ],
        ]);
    }

    /**
     * 获取投诉类型列表
     * 
     * GET /api/v2/complaints/types
     */
    public function types(): JsonResponse
    {
        $types = collect(Complaint::getTypes())->map(function ($label, $value) {
            return [
                'value' => $value,
                'label' => $label,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * 撤销投诉（仅限待处理状态）
     * 
     * DELETE /api/v2/complaints/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $complaint = Complaint::where('user_id', Auth::id())
            ->where('status', Complaint::STATUS_PENDING)
            ->findOrFail($id);

        $complaint->update(['status' => Complaint::STATUS_CLOSED]);

        return response()->json([
            'success' => true,
            'message' => '投诉已撤销',
        ]);
    }

    /**
     * 管理员：获取所有投诉列表
     * 
     * GET /api/v2/admin/complaints
     */
    public function adminIndex(Request $request): JsonResponse
    {
        // TODO: 添加管理员权限检查
        
        $perPage = $request->input('per_page', 20);
        $status = $request->input('status');
        $type = $request->input('type');

        $query = Complaint::with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $complaints = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $complaints->map(function ($complaint) {
                return [
                    'id' => $complaint->id,
                    'user' => [
                        'id' => $complaint->user->id,
                        'name' => $complaint->user->name,
                        'email' => $complaint->user->email,
                    ],
                    'type' => $complaint->type,
                    'type_label' => $complaint->type_label,
                    'content' => $complaint->content,
                    'status' => $complaint->status,
                    'status_label' => $complaint->status_label,
                    'created_at' => $complaint->created_at->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $complaints->currentPage(),
                'last_page' => $complaints->lastPage(),
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
            ],
            'stats' => [
                'pending' => Complaint::pending()->count(),
                'processing' => Complaint::processing()->count(),
                'handled' => Complaint::handled()->count(),
            ],
        ]);
    }

    /**
     * 管理员：处理投诉
     * 
     * PUT /api/v2/admin/complaints/{id}
     */
    public function adminUpdate(Request $request, int $id): JsonResponse
    {
        // TODO: 添加管理员权限检查
        
        $validated = $request->validate([
            'status' => ['required', Rule::in(['processing', 'resolved', 'rejected'])],
            'response' => 'required_if:status,resolved,rejected|string|max:2000',
        ]);

        $complaint = Complaint::findOrFail($id);

        switch ($validated['status']) {
            case 'processing':
                $complaint->markAsProcessing('admin'); // TODO: 使用实际管理员ID
                break;
            case 'resolved':
                $complaint->markAsResolved($validated['response']);
                break;
            case 'rejected':
                $complaint->markAsRejected($validated['response']);
                break;
        }

        // 记录日志
        Log::info('投诉已处理', [
            'complaint_id' => $complaint->id,
            'status' => $validated['status'],
            'handler' => 'admin',
        ]);

        return response()->json([
            'success' => true,
            'message' => '投诉已处理',
            'data' => [
                'id' => $complaint->id,
                'status' => $complaint->status,
                'status_label' => $complaint->status_label,
            ],
        ]);
    }
}
