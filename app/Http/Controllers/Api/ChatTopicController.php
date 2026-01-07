<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatTopic;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * ChatTopicController - AI聊天话题管理
 * 
 * 提供话题的增删查改接口，用于组织用户的AI对话
 * 
 * @version 1.0.0
 * @date 2025-01-02
 */
class ChatTopicController extends Controller
{
    /**
     * 获取话题列表
     * GET /api/chat/topics
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $topics = ChatTopic::where('user_id', $user->id)
                ->orderBy('last_message_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => $topics->map(function ($topic) {
                    return [
                        'id' => (string) $topic->id,
                        'name' => $topic->name,
                        'createdAt' => $topic->created_at->toIso8601String(),
                        'updatedAt' => $topic->updated_at->toIso8601String(),
                        'messageCount' => $topic->message_count,
                        'lastMessage' => $topic->last_message,
                        'lastMessageAt' => $topic->last_message_at?->toIso8601String(),
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('获取话题列表失败', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return response()->json([
                'code' => 500,
                'msg' => '获取话题列表失败',
                'data' => null
            ], 500);
        }
    }
    
    /**
     * 创建新话题
     * POST /api/chat/topics
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
            ]);
            
            $user = $request->user();
            
            $topic = ChatTopic::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'message_count' => 0,
            ]);
            
            Log::info('创建话题成功', [
                'topic_id' => $topic->id,
                'user_id' => $user->id,
                'name' => $topic->name,
            ]);
            
            return response()->json([
                'code' => 200,
                'msg' => '创建成功',
                'data' => [
                    'id' => (string) $topic->id,
                    'name' => $topic->name,
                    'createdAt' => $topic->created_at->toIso8601String(),
                    'updatedAt' => $topic->updated_at->toIso8601String(),
                    'messageCount' => 0,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'msg' => '参数验证失败',
                'data' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('创建话题失败', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return response()->json([
                'code' => 500,
                'msg' => '创建话题失败',
                'data' => null
            ], 500);
        }
    }
    
    /**
     * 获取话题详情
     * GET /api/chat/topics/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $topic = ChatTopic::where('user_id', $user->id)
                ->findOrFail($id);
            
            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => [
                    'id' => (string) $topic->id,
                    'name' => $topic->name,
                    'description' => $topic->description,
                    'createdAt' => $topic->created_at->toIso8601String(),
                    'updatedAt' => $topic->updated_at->toIso8601String(),
                    'messageCount' => $topic->message_count,
                    'lastMessage' => $topic->last_message,
                    'lastMessageAt' => $topic->last_message_at?->toIso8601String(),
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'code' => 404,
                'msg' => '话题不存在',
                'data' => null
            ], 404);
        } catch (\Exception $e) {
            Log::error('获取话题详情失败', [
                'error' => $e->getMessage(),
                'topic_id' => $id,
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return response()->json([
                'code' => 500,
                'msg' => '获取话题详情失败',
                'data' => null
            ], 500);
        }
    }
    
    /**
     * 更新话题
     * PUT /api/chat/topics/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:100',
                'description' => 'nullable|string',
            ]);
            
            $user = $request->user();
            
            $topic = ChatTopic::where('user_id', $user->id)
                ->findOrFail($id);
            
            $topic->update($validated);
            
            Log::info('更新话题成功', [
                'topic_id' => $topic->id,
                'user_id' => $user->id,
            ]);
            
            return response()->json([
                'code' => 200,
                'msg' => '更新成功',
                'data' => [
                    'id' => (string) $topic->id,
                    'name' => $topic->name,
                    'description' => $topic->description,
                    'updatedAt' => $topic->updated_at->toIso8601String(),
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'code' => 404,
                'msg' => '话题不存在',
                'data' => null
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'msg' => '参数验证失败',
                'data' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('更新话题失败', [
                'error' => $e->getMessage(),
                'topic_id' => $id,
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return response()->json([
                'code' => 500,
                'msg' => '更新话题失败',
                'data' => null
            ], 500);
        }
    }
    
    /**
     * 删除话题
     * DELETE /api/chat/topics/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $topic = ChatTopic::where('user_id', $user->id)
                ->findOrFail($id);
            
            $topic->delete();
            
            Log::info('删除话题成功', [
                'topic_id' => $id,
                'user_id' => $user->id,
            ]);
            
            return response()->json([
                'code' => 200,
                'msg' => '删除成功',
                'data' => null
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'code' => 404,
                'msg' => '话题不存在',
                'data' => null
            ], 404);
        } catch (\Exception $e) {
            Log::error('删除话题失败', [
                'error' => $e->getMessage(),
                'topic_id' => $id,
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return response()->json([
                'code' => 500,
                'msg' => '删除话题失败',
                'data' => null
            ], 500);
        }
    }
    
    /**
     * 获取话题消息列表
     * GET /api/chat/topics/{id}/messages
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $topic = ChatTopic::where('user_id', $user->id)
                ->findOrFail($id);
            
            // 从chat_messages表获取消息
            $messages = \App\Models\ChatMessage::where('topic_id', $id)
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'asc')
                ->get();
            
            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => $messages->map(function ($msg) {
                    return [
                        'id' => (string) $msg->id,
                        'topicId' => (string) $msg->topic_id,
                        'role' => $msg->role,
                        'content' => $msg->content,
                        'timestamp' => $msg->created_at->timestamp * 1000,
                        'toolCalls' => $msg->metadata['tool_calls'] ?? null,
                        'trainingPlan' => $msg->metadata['training_plan'] ?? null,
                    ];
                })
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'code' => 404,
                'msg' => '话题不存在',
                'data' => null
            ], 404);
        } catch (\Exception $e) {
            Log::error('获取话题消息失败', [
                'error' => $e->getMessage(),
                'topic_id' => $id,
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return response()->json([
                'code' => 500,
                'msg' => '获取话题消息失败',
                'data' => null
            ], 500);
        }
    }
    
    /**
     * 保存消息
     * POST /api/chat/topics/{id}/messages
     */
    public function storeMessage(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'role' => 'required|in:user,assistant,system',
                'content' => 'required|string',
                'client_id' => 'nullable|string|max:64',
                'metadata' => 'nullable|array',
            ]);
            
            $user = $request->user();
            
            $topic = ChatTopic::where('user_id', $user->id)
                ->findOrFail($id);
            
            // 检查client_id是否已存在（去重）
            if (!empty($validated['client_id'])) {
                $existing = \App\Models\ChatMessage::where('client_id', $validated['client_id'])->first();
                if ($existing) {
                    return response()->json([
                        'code' => 200,
                        'msg' => '消息已存在',
                        'data' => [
                            'id' => (string) $existing->id,
                            'topicId' => (string) $existing->topic_id,
                            'role' => $existing->role,
                            'content' => $existing->content,
                            'timestamp' => $existing->created_at->timestamp * 1000,
                        ]
                    ]);
                }
            }
            
            $message = \App\Models\ChatMessage::create([
                'topic_id' => $id,
                'user_id' => $user->id,
                'role' => $validated['role'],
                'content' => $validated['content'],
                'client_id' => $validated['client_id'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
            ]);
            
            // 更新话题统计
            $topic->increment('message_count');
            $topic->update([
                'last_message' => mb_substr($validated['content'], 0, 50),
                'last_message_at' => now(),
            ]);
            
            return response()->json([
                'code' => 200,
                'msg' => '保存成功',
                'data' => [
                    'id' => (string) $message->id,
                    'topicId' => (string) $message->topic_id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'timestamp' => $message->created_at->timestamp * 1000,
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'code' => 404,
                'msg' => '话题不存在',
                'data' => null
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'msg' => '参数验证失败',
                'data' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('保存消息失败', [
                'error' => $e->getMessage(),
                'topic_id' => $id,
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return response()->json([
                'code' => 500,
                'msg' => '保存消息失败',
                'data' => null
            ], 500);
        }
    }
    
    /**
     * 批量同步消息（从本地缓存同步到后端）
     * POST /api/chat/topics/{id}/messages/sync
     */
    public function syncMessages(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'messages' => 'required|array',
                'messages.*.role' => 'required|in:user,assistant,system',
                'messages.*.content' => 'required|string',
                'messages.*.client_id' => 'required|string|max:64',
                'messages.*.timestamp' => 'nullable|integer',
                'messages.*.metadata' => 'nullable|array',
            ]);
            
            $user = $request->user();
            
            $topic = ChatTopic::where('user_id', $user->id)
                ->findOrFail($id);
            
            $synced = [];
            $skipped = [];
            
            foreach ($validated['messages'] as $msgData) {
                // 检查是否已存在
                $existing = \App\Models\ChatMessage::where('client_id', $msgData['client_id'])->first();
                if ($existing) {
                    $skipped[] = $msgData['client_id'];
                    continue;
                }
                
                $message = \App\Models\ChatMessage::create([
                    'topic_id' => $id,
                    'user_id' => $user->id,
                    'role' => $msgData['role'],
                    'content' => $msgData['content'],
                    'client_id' => $msgData['client_id'],
                    'metadata' => $msgData['metadata'] ?? null,
                ]);
                
                $synced[] = [
                    'client_id' => $msgData['client_id'],
                    'server_id' => (string) $message->id,
                ];
            }
            
            // 更新话题统计
            if (count($synced) > 0) {
                $topic->increment('message_count', count($synced));
                $lastMsg = end($validated['messages']);
                $topic->update([
                    'last_message' => mb_substr($lastMsg['content'], 0, 50),
                    'last_message_at' => now(),
                ]);
            }
            
            return response()->json([
                'code' => 200,
                'msg' => '同步完成',
                'data' => [
                    'synced' => $synced,
                    'skipped' => $skipped,
                    'synced_count' => count($synced),
                    'skipped_count' => count($skipped),
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'code' => 404,
                'msg' => '话题不存在',
                'data' => null
            ], 404);
        } catch (\Exception $e) {
            Log::error('同步消息失败', [
                'error' => $e->getMessage(),
                'topic_id' => $id,
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return response()->json([
                'code' => 500,
                'msg' => '同步消息失败',
                'data' => null
            ], 500);
        }
    }
    
    /**
     * 从metadata中提取工具调用信息
     */
    private function extractToolCalls($session): ?array
    {
        if (!isset($session->metadata['dag_execution'])) {
            return null;
        }
        
        $dagExecution = $session->metadata['dag_execution'];
        $toolCalls = [];
        
        if (isset($dagExecution['tools']) && is_array($dagExecution['tools'])) {
            foreach ($dagExecution['tools'] as $index => $tool) {
                $toolCalls[] = [
                    'id' => "tool-{$index}",
                    'name' => $tool['name'] ?? $tool['tool_name'] ?? 'unknown',
                    'displayName' => $tool['display_name'] ?? $tool['name'] ?? 'unknown',
                    'status' => $tool['status'] ?? 'success',
                    'startTime' => $tool['start_time'] ?? time() * 1000,
                    'endTime' => $tool['end_time'] ?? null,
                    'duration' => $tool['duration'] ?? null,
                    'parameters' => $tool['parameters'] ?? null,
                    'result' => $tool['result'] ?? null,
                    'error' => $tool['error'] ?? null,
                    'dataSource' => $tool['data_source'] ?? $tool['metadata']['data_source'] ?? null,
                ];
            }
        }
        
        return !empty($toolCalls) ? $toolCalls : null;
    }
    
    /**
     * 从metadata中提取训练计划
     */
    private function extractTrainingPlan($session): ?array
    {
        if (!isset($session->metadata['training_plan'])) {
            return null;
        }
        
        return $session->metadata['training_plan'];
    }
}
