<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\ChatTopic;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * ChatTopicController - AI聊天话题管理
 * 
 * 提供话题的增删查改接口，用于组织用户的AI对话
 * 包含历史对话和会话管理功能
 * 
 * @version 2.0.0
 * @date 2026-01-11
 * @requirements 1.1-1.6 对话历史与上下文管理
 */
class ChatTopicController extends BaseController
{
    /**
     * 获取用户对话历史
     * GET /api/chat/history
     * 
     * 返回用户的对话历史，支持分页和按话题筛选
     * 
     * @requirements 1.2 检索用户最近的对话历史
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $topicId = $request->get('topic_id');
            $limit = min($request->get('limit', 20), 100);
            $offset = $request->get('offset', 0);
            $sessionId = $request->get('session_id');
            
            // 构建查询
            $query = ChatSession::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');
            
            // 按话题筛选
            if ($topicId) {
                $query->where('topic_id', $topicId);
            }
            
            // 按会话筛选
            if ($sessionId) {
                $query->where('session_id', $sessionId);
            }
            
            // 获取总数
            $total = $query->count();
            
            // 分页获取
            $sessions = $query->skip($offset)->take($limit)->get();
            
            // 格式化返回数据
            $history = $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'sessionId' => $session->session_id,
                    'topicId' => $session->topic_id,
                    'userQuery' => $session->user_query,
                    'llmResponse' => $session->llm_response,
                    'modelUsed' => $session->model_used,
                    'toolsUsed' => $session->tools_used,
                    'userRating' => $session->user_rating,
                    'userFeedback' => $session->user_feedback,
                    'metadata' => $session->metadata,
                    'createdAt' => $session->created_at->toIso8601String(),
                    'updatedAt' => $session->updated_at->toIso8601String(),
                ];
            });
            
            return $this->success([
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'history' => $history,
            ], '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取对话历史');
        }
    }
    
    /**
     * 获取用户会话列表
     * GET /api/chat/sessions
     * 
     * 返回用户的会话列表，按session_id分组
     * 
     * @requirements 1.5 创建新会话ID并初始化
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function sessions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $limit = min($request->get('limit', 20), 100);
            $offset = $request->get('offset', 0);
            
            // 按session_id分组，获取每个会话的最新记录
            $sessionsQuery = ChatSession::where('user_id', $user->id)
                ->select('session_id', DB::raw('MAX(id) as latest_id'))
                ->groupBy('session_id')
                ->orderBy('latest_id', 'desc');
            
            // 获取总数
            $total = $sessionsQuery->get()->count();
            
            // 分页获取
            $sessionGroups = $sessionsQuery->skip($offset)->take($limit)->get();
            
            // 获取完整的会话信息
            $latestIds = $sessionGroups->pluck('latest_id');
            $latestSessions = ChatSession::whereIn('id', $latestIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->keyBy('session_id');
            
            // 获取每个会话的消息数量
            $messageCounts = ChatSession::where('user_id', $user->id)
                ->whereIn('session_id', $sessionGroups->pluck('session_id'))
                ->select('session_id', DB::raw('COUNT(*) as count'))
                ->groupBy('session_id')
                ->get()
                ->keyBy('session_id');
            
            // 格式化返回数据
            $sessions = $sessionGroups->map(function ($group) use ($latestSessions, $messageCounts) {
                $session = $latestSessions->get($group->session_id);
                $count = $messageCounts->get($group->session_id);
                
                if (!$session) {
                    return null;
                }
                
                // 生成会话标题（取第一条用户问题的前50个字符）
                $firstQuery = ChatSession::where('session_id', $group->session_id)
                    ->orderBy('created_at', 'asc')
                    ->value('user_query');
                $title = $firstQuery ? mb_substr($firstQuery, 0, 50) : '新对话';
                
                return [
                    'sessionId' => $session->session_id,
                    'title' => $title,
                    'topicId' => $session->topic_id,
                    'messageCount' => $count ? $count->count : 0,
                    'lastQuery' => mb_substr($session->user_query, 0, 100),
                    'lastResponse' => mb_substr($session->llm_response, 0, 100),
                    'modelUsed' => $session->model_used,
                    'createdAt' => $session->created_at->toIso8601String(),
                    'updatedAt' => $session->updated_at->toIso8601String(),
                ];
            })->filter()->values();
            
            return $this->success([
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'sessions' => $sessions,
            ], '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取会话列表');
        }
    }
    
    /**
     * 获取单个会话的详细对话
     * GET /api/chat/sessions/{sessionId}
     * 
     * 返回指定会话的所有对话记录
     * 
     * @param Request $request
     * @param string $sessionId
     * @return JsonResponse
     */
    public function sessionDetail(Request $request, string $sessionId): JsonResponse
    {
        try {
            $user = $request->user();
            
            // 获取会话的所有对话
            $conversations = ChatSession::where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->orderBy('created_at', 'asc')
                ->get();
            
            if ($conversations->isEmpty()) {
                return $this->fail('会话不存在', 404);
            }
            
            // 格式化为消息列表
            $messages = [];
            foreach ($conversations as $conv) {
                // 用户消息
                $messages[] = [
                    'id' => "user-{$conv->id}",
                    'role' => 'user',
                    'content' => $conv->user_query,
                    'timestamp' => $conv->created_at->timestamp * 1000,
                ];
                
                // AI回复
                $messages[] = [
                    'id' => "assistant-{$conv->id}",
                    'role' => 'assistant',
                    'content' => $conv->llm_response,
                    'timestamp' => $conv->created_at->timestamp * 1000 + 1,
                    'modelUsed' => $conv->model_used,
                    'toolsUsed' => $conv->tools_used,
                    'metadata' => $conv->metadata,
                ];
            }
            
            $firstConv = $conversations->first();
            $lastConv = $conversations->last();
            
            return $this->success([
                'sessionId' => $sessionId,
                'topicId' => $firstConv->topic_id,
                'messageCount' => count($messages),
                'messages' => $messages,
                'createdAt' => $firstConv->created_at->toIso8601String(),
                'updatedAt' => $lastConv->updated_at->toIso8601String(),
            ], '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取会话详情');
        }
    }
    
    /**
     * 删除会话
     * DELETE /api/chat/sessions/{sessionId}
     * 
     * 删除指定会话的所有对话记录
     * 
     * @param Request $request
     * @param string $sessionId
     * @return JsonResponse
     */
    public function deleteSession(Request $request, string $sessionId): JsonResponse
    {
        try {
            $user = $request->user();
            
            // 检查会话是否存在
            $count = ChatSession::where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->count();
            
            if ($count === 0) {
                return $this->fail('会话不存在', 404);
            }
            
            // 删除会话的所有记录
            $deleted = ChatSession::where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->delete();
            
            Log::info('删除会话成功', [
                'session_id' => $sessionId,
                'user_id' => $user->id,
                'deleted_count' => $deleted,
            ]);
            
            return $this->success([
                'sessionId' => $sessionId,
                'deletedCount' => $deleted,
            ], '删除成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '删除会话');
        }
    }
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
            
            return $this->success($topics->map(function ($topic) {
                return [
                    'id' => (string) $topic->id,
                    'name' => $topic->name,
                    'createdAt' => $topic->created_at->toIso8601String(),
                    'updatedAt' => $topic->updated_at->toIso8601String(),
                    'messageCount' => $topic->message_count,
                    'lastMessage' => $topic->last_message,
                    'lastMessageAt' => $topic->last_message_at?->toIso8601String(),
                ];
            }), '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取话题列表');
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
            
            return $this->success([
                'id' => (string) $topic->id,
                'name' => $topic->name,
                'createdAt' => $topic->created_at->toIso8601String(),
                'updatedAt' => $topic->updated_at->toIso8601String(),
                'messageCount' => 0,
            ], '创建成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '创建话题');
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
            return $this->fail('话题不存在', 404);
        } catch (\Exception $e) {
            Log::error('获取话题详情失败', [
                'error' => $e->getMessage(),
                'topic_id' => $id,
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return $this->fail('获取话题详情失败', 500);
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
            return $this->fail('话题不存在', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('参数验证失败', 422, $e->errors()
            );
        } catch (\Exception $e) {
            Log::error('更新话题失败', [
                'error' => $e->getMessage(),
                'topic_id' => $id,
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return $this->fail('更新话题失败', 500);
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
            
            return $this->success(null
            , '删除成功');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->fail('话题不存在', 404);
        } catch (\Exception $e) {
            Log::error('删除话题失败', [
                'error' => $e->getMessage(),
                'topic_id' => $id,
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return $this->fail('删除话题失败', 500);
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
            return $this->fail('话题不存在', 404);
        } catch (\Exception $e) {
            Log::error('获取话题消息失败', [
                'error' => $e->getMessage(),
                'topic_id' => $id,
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return $this->fail('获取话题消息失败', 500);
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
            return $this->fail('话题不存在', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('参数验证失败', 422, $e->errors()
            );
        } catch (\Exception $e) {
            Log::error('保存消息失败', [
                'error' => $e->getMessage(),
                'topic_id' => $id,
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return $this->fail('保存消息失败', 500);
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
            return $this->fail('话题不存在', 404);
        } catch (\Exception $e) {
            Log::error('同步消息失败', [
                'error' => $e->getMessage(),
                'topic_id' => $id,
                'user_id' => $request->user()->id ?? null,
            ]);
            
            return $this->fail('同步消息失败', 500);
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
