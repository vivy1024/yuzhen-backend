<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Thread 管理控制器
 * 
 * 管理 AI 对话线程（thread_id），对应 DAML-RAG v2 的 LangGraph checkpointer。
 * 每个 thread 代表一个持久化的对话上下文。
 *
 * @version 1.0.0
 * @created 2026-05-09
 */
class ThreadController extends BaseController
{
    /**
     * 创建新线程
     * 
     * POST /api/ai/v1/thread/create
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $userId = $request->user()->id ?? $request->input('user_id');
        
        if (!$userId) {
            return $this->fail('用户ID不能为空', 400);
        }

        $threadId = 'thread_' . Str::uuid()->toString();

        // 存储到数据库
        try {
            \DB::table('chat_threads')->insert([
                'thread_id' => $threadId,
                'user_id' => $userId,
                'title' => $request->input('title', '新对话'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->success([
                'thread_id' => $threadId,
                'title' => $request->input('title', '新对话'),
                'created_at' => now()->toISOString(),
            ], '线程创建成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '创建线程');
        }
    }

    /**
     * 列出用户线程
     * 
     * GET /api/ai/v1/thread/list
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $userId = $request->user()->id ?? $request->query('user_id');
        
        if (!$userId) {
            return $this->fail('用户ID不能为空', 400);
        }

        try {
            $threads = \DB::table('chat_threads')
                ->where('user_id', $userId)
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get(['thread_id', 'title', 'created_at', 'updated_at']);

            return $this->success([
                'threads' => $threads,
                'total' => $threads->count(),
            ]);

        } catch (\Exception $e) {
            return $this->handleException($e, '获取线程列表');
        }
    }

    /**
     * 删除线程
     * 
     * DELETE /api/ai/v1/thread/{threadId}
     * 
     * @param Request $request
     * @param string $threadId
     * @return JsonResponse
     */
    public function delete(Request $request, string $threadId): JsonResponse
    {
        $userId = $request->user()->id ?? $request->query('user_id');
        
        if (!$userId) {
            return $this->fail('用户ID不能为空', 400);
        }

        try {
            $deleted = \DB::table('chat_threads')
                ->where('thread_id', $threadId)
                ->where('user_id', $userId)
                ->delete();

            if ($deleted === 0) {
                return $this->fail('线程不存在或无权限', 404);
            }

            return $this->success(null, '线程已删除');

        } catch (\Exception $e) {
            return $this->handleException($e, '删除线程');
        }
    }
}
