<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HITL 审批控制器
 * 
 * 处理 Agent 高风险操作的用户确认/拒绝回调。
 * 当 DAML-RAG Agent 检测到高风险操作时会暂停（interrupt），
 * 前端展示确认弹窗，用户响应后通过此接口回调 Agent 恢复执行。
 *
 * @version 1.0.0
 * @created 2026-05-09
 */
class ApprovalController extends BaseController
{
    /**
     * DAML-RAG 服务地址
     */
    private string $damlRagUrl;

    public function __construct()
    {
        $this->damlRagUrl = config('services.daml_rag.url', 'http://fitness_daml_rag:8001');
    }

    /**
     * 响应审批请求
     * 
     * POST /api/ai/v1/approval/respond
     * 
     * 将用户的审批决定转发给 DAML-RAG Agent，恢复被中断的执行。
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function respond(Request $request): JsonResponse
    {
        $request->validate([
            'thread_id' => ['required', 'string', 'max:128'],
            'approved'  => ['required', 'boolean'],
            'user_id'   => ['required', 'integer', 'min:1'],
        ]);

        $threadId = $request->input('thread_id');
        $approved = $request->input('approved');
        $userId = $request->input('user_id');

        Log::info('[Approval] 用户审批响应', [
            'thread_id' => $threadId,
            'approved' => $approved,
            'user_id' => $userId,
        ]);

        try {
            // 转发到 DAML-RAG 的 approval 端点
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => $request->header('Authorization'),
                    'X-Internal-Token' => $request->header('X-Internal-Token'),
                ])
                ->post("{$this->damlRagUrl}/api/v1/approval/respond", [
                    'thread_id' => $threadId,
                    'approved' => $approved,
                    'user_id' => $userId,
                ]);

            if ($response->successful()) {
                return $this->success(
                    $response->json('data'),
                    $approved ? '已确认，继续执行' : '已选择保守方案'
                );
            }

            return $this->fail(
                $response->json('error', 'Agent 响应失败'),
                $response->status()
            );

        } catch (\Exception $e) {
            return $this->handleException($e, '审批响应');
        }
    }

    /**
     * 查询待审批状态
     * 
     * GET /api/ai/v1/approval/pending?thread_id=xxx
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function pending(Request $request): JsonResponse
    {
        $request->validate([
            'thread_id' => ['required', 'string', 'max:128'],
        ]);

        $threadId = $request->query('thread_id');

        try {
            $response = Http::timeout(10)
                ->get("{$this->damlRagUrl}/api/v1/approval/pending", [
                    'thread_id' => $threadId,
                ]);

            if ($response->successful()) {
                return $this->success($response->json('data'));
            }

            return $this->success([
                'has_pending' => false,
                'thread_id' => $threadId,
            ]);

        } catch (\Exception $e) {
            // 查询失败不算严重错误
            return $this->success([
                'has_pending' => false,
                'thread_id' => $threadId,
                'note' => 'Agent 服务暂时不可用',
            ]);
        }
    }
}
