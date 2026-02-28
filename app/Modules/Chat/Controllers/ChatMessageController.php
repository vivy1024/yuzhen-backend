<?php

namespace App\Modules\Chat\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\ChatSession;
use App\Models\ChatTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * ChatMessageController — 消息/话题管理 API
 *
 * 从 InternalChatController 拆分，负责话题和消息的 CRUD。
 */
class ChatMessageController extends BaseController
{
    public function saveTopic(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'topic_id' => 'required|string|max:100',
                'user_id' => 'nullable|string|max:50',
                'name' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422, 'msg' => '验证失败',
                    'data' => ['errors' => $validator->errors()]
                ], 422);
            }

            $topicId = $request->input('topic_id');
            $userId = $request->input('user_id');
            $name = $request->input('name', '新对话');

            $numericUserId = null;
            if ($userId && $userId !== 'guest' && is_numeric($userId)) {
                $numericUserId = (int) $userId;
            }

            $topic = ChatTopic::where('id', $topicId)
                ->orWhere(function ($query) use ($topicId, $numericUserId) {
                    $query->where('name', $topicId);
                    if ($numericUserId) {
                        $query->where('user_id', $numericUserId);
                    }
                })
                ->first();

            if (!$topic) {
                $topic = ChatTopic::create([
                    'user_id' => $numericUserId ?? 0,
                    'name' => $name,
                    'description' => "Topic ID: {$topicId}",
                    'message_count' => 0,
                ]);

                Log::info('Chat topic created', [
                    'id' => $topic->id, 'topic_id' => $topicId, 'user_id' => $userId,
                ]);
            }

            return response()->json([
                'code' => 200, 'msg' => '话题保存成功',
                'data' => [
                    'id' => $topic->id, 'topic_id' => $topicId,
                    'name' => $topic->name, 'message_count' => $topic->message_count,
                    'created_at' => $topic->created_at->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save chat topic', [
                'topic_id' => $request->input('topic_id', 'unknown'),
                'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500, 'msg' => '保存失败: ' . $e->getMessage(), 'data' => null
            ], 500);
        }
    }

    public function saveMessage(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'topic_id' => 'required|string|max:100',
                'user_id' => 'nullable|string|max:50',
                'role' => 'required|string|in:user,assistant',
                'content' => 'required|string|max:50000',
                'session_id' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422, 'msg' => '验证失败',
                    'data' => ['errors' => $validator->errors()]
                ], 422);
            }

            $topicId = $request->input('topic_id');
            $userId = $request->input('user_id');
            $role = $request->input('role');
            $content = $request->input('content');
            $sessionId = $request->input('session_id');

            $numericUserId = null;
            if ($userId && $userId !== 'guest' && is_numeric($userId)) {
                $numericUserId = (int) $userId;
            }

            $topic = ChatTopic::where('id', $topicId)
                ->orWhere('name', $topicId)
                ->first();

            if (!$topic) {
                $topic = ChatTopic::create([
                    'user_id' => $numericUserId ?? 0,
                    'name' => '新对话',
                    'description' => "Topic ID: {$topicId}",
                    'message_count' => 0,
                ]);
            }

            $message = \App\Models\ChatMessage::create([
                'topic_id' => $topic->id,
                'user_id' => $numericUserId ?? 0,
                'role' => $role,
                'content' => $content,
                'client_id' => $sessionId,
                'metadata' => null,
            ]);

            $topic->increment('message_count');
            $topic->update([
                'last_message' => mb_substr($content, 0, 50),
                'last_message_at' => now(),
            ]);

            if ($role === 'assistant' && $sessionId) {
                ChatSession::where('session_id', $sessionId)
                    ->update(['topic_id' => $topic->id]);
            }

            Log::info('Chat message saved', [
                'message_id' => $message->id, 'topic_id' => $topic->id,
                'role' => $role, 'content_length' => strlen($content),
                'session_id' => $sessionId,
            ]);

            return response()->json([
                'code' => 200, 'msg' => '消息保存成功',
                'data' => [
                    'message_id' => $message->id, 'topic_id' => $topic->id,
                    'role' => $role, 'message_count' => $topic->message_count,
                    'saved_at' => now()->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save chat message', [
                'topic_id' => $request->input('topic_id', 'unknown'),
                'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500, 'msg' => '保存失败: ' . $e->getMessage(), 'data' => null
            ], 500);
        }
    }

    public function getTopic(string $topicId, Request $request): JsonResponse
    {
        try {
            $userId = $request->query('user_id');

            $topic = ChatTopic::where('id', $topicId)
                ->orWhere('name', $topicId)
                ->first();

            if (!$topic) {
                return response()->json([
                    'code' => 404, 'msg' => '话题不存在', 'data' => null
                ], 404);
            }

            $messages = \App\Models\ChatMessage::where('topic_id', $topic->id)
                ->orderBy('created_at', 'asc')
                ->limit(20)
                ->get()
                ->map(function ($msg) {
                    return [
                        'role' => $msg->role,
                        'content' => $msg->content,
                        'timestamp' => $msg->created_at->toIso8601String(),
                        'metadata' => [],
                    ];
                })
                ->toArray();

            return response()->json([
                'code' => 200, 'msg' => '获取成功',
                'data' => [
                    'topic_id' => $topicId,
                    'title' => $topic->name,
                    'messages' => $messages,
                    'metadata' => [
                        'message_count' => $topic->message_count,
                        'created_at' => $topic->created_at->toIso8601String(),
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get chat topic', [
                'topic_id' => $topicId, 'error' => $e->getMessage(),
            ]);

            return response()->json([
                'code' => 500, 'msg' => '获取失败: ' . $e->getMessage(), 'data' => null
            ], 500);
        }
    }

    public function clearTopic(string $topicId): JsonResponse
    {
        try {
            $topic = ChatTopic::where('id', $topicId)
                ->orWhere('name', $topicId)
                ->first();

            if (!$topic) {
                return response()->json([
                    'code' => 404, 'msg' => '话题不存在', 'data' => null
                ], 404);
            }

            $deletedCount = ChatSession::where('topic_id', $topic->id)->delete();

            $topic->update([
                'message_count' => 0,
                'last_message' => null,
                'last_message_at' => null,
            ]);

            Log::info('Chat topic cleared', [
                'topic_id' => $topic->id, 'deleted_sessions' => $deletedCount,
            ]);

            return response()->json([
                'code' => 200, 'msg' => '话题清空成功',
                'data' => ['topic_id' => $topic->id, 'deleted_sessions' => $deletedCount]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear chat topic', [
                'topic_id' => $topicId, 'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500, 'msg' => '清空失败: ' . $e->getMessage(), 'data' => null
            ], 500);
        }
    }
}
