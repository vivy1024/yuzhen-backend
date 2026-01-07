<?php

namespace App\Modules\Chat\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\ChatTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Internal Chat API Controller - MCO服务内部调用
 * 
 * 提供对话记录存储API，供MCO服务调用
 * 
 * 认证方式：X-Internal-Token
 * 
 * @version 1.1.0
 * @date 2025-11-05
 */
class InternalChatController extends Controller
{
    /**
     * 保存对话记录
     * 
     * POST /api/internal/chat/save-session
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function saveChatSession(Request $request): JsonResponse
    {
        try {
            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|string|max:36',
                'user_id' => 'nullable|integer|exists:users,id',
                'user_query' => 'required|string|max:10000',
                'llm_response' => 'required|string|max:50000',
                'model_used' => 'required|string|max:50',
                'tools_used' => 'nullable|array',
                'metadata' => 'nullable|array',
                'qdrant_point_id' => 'nullable|string|max:36',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'msg' => '验证失败',
                    'data' => [
                        'errors' => $validator->errors()
                    ]
                ], 422);
            }

            // 创建对话记录
            $chatSession = ChatSession::create([
                'session_id' => $request->session_id,
                'user_id' => $request->user_id,
                'user_query' => $request->user_query,
                'llm_response' => $request->llm_response,
                'model_used' => $request->model_used,
                'tools_used' => $request->tools_used,
                'metadata' => $request->metadata,
                'qdrant_point_id' => $request->qdrant_point_id,
            ]);

            Log::info('Chat session saved', [
                'id' => $chatSession->id,
                'session_id' => $chatSession->session_id,
                'user_id' => $chatSession->user_id,
                'model_used' => $chatSession->model_used,
            ]);

            return response()->json([
                'code' => 200,
                'msg' => '保存成功',
                'data' => [
                    'id' => $chatSession->id,
                    'session_id' => $chatSession->session_id,
                    'created_at' => $chatSession->created_at->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save chat session', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '保存失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 更新对话反馈（用户评分）
     * 
     * POST /api/internal/chat/update-feedback
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateFeedback(Request $request): JsonResponse
    {
        try {
            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|string|max:36',
                'reward' => 'required|numeric|min:1|max:5',
                'feedback_text' => 'nullable|string|max:500',  // 与Migration保持一致
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'msg' => '验证失败',
                    'data' => [
                        'errors' => $validator->errors()
                    ]
                ], 422);
            }

            // 查找并更新对话记录
            $chatSession = ChatSession::where('session_id', $request->session_id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$chatSession) {
                return response()->json([
                    'code' => 404,
                    'msg' => '对话记录不存在',
                    'data' => null
                ], 404);
            }

            // 更新反馈
            $chatSession->update([
                'user_rating' => (int) $request->reward,
                'user_feedback' => $request->feedback_text,
            ]);

            Log::info('Chat feedback updated', [
                'id' => $chatSession->id,
                'session_id' => $chatSession->session_id,
                'user_rating' => $chatSession->user_rating,
            ]);

            return response()->json([
                'code' => 200,
                'msg' => '反馈更新成功',
                'data' => [
                    'id' => $chatSession->id,
                    'session_id' => $chatSession->session_id,
                    'user_rating' => $chatSession->user_rating,
                    'is_high_quality' => $chatSession->isHighQuality(),
                    'qdrant_point_id' => $chatSession->qdrant_point_id,
                    'updated_at' => $chatSession->updated_at->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update feedback', [
                'session_id' => $request->session_id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '更新失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 获取用户对话历史
     * 
     * GET /api/internal/chat/user/{userId}/history
     * 
     * @param int $userId
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserChatHistory(int $userId, Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 20);
            $sessionId = $request->get('session_id');

            $query = ChatSession::byUser($userId)
                ->orderBy('created_at', 'desc');

            if ($sessionId) {
                $query->bySession($sessionId);
            }

            $chatSessions = $query->limit($limit)->get();

            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => [
                    'count' => $chatSessions->count(),
                    'sessions' => $chatSessions->map(function ($session) {
                        return [
                            'id' => $session->id,
                            'session_id' => $session->session_id,
                            'user_query' => $session->user_query,
                            'llm_response' => $session->llm_response,
                            'model_used' => $session->model_used,
                            'tools_used' => $session->tools_used,
                            'user_rating' => $session->user_rating,
                            'created_at' => $session->created_at->toIso8601String(),
                        ];
                    }),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get chat history', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '获取失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 获取高质量对话（用于Few-Shot学习）
     * 
     * GET /api/internal/chat/high-quality
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getHighQualitySessions(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);
            $modelUsed = $request->get('model_used');

            $query = ChatSession::highQuality()
                ->orderBy('user_rating', 'desc')
                ->orderBy('created_at', 'desc');

            if ($modelUsed) {
                $query->byModel($modelUsed);
            }

            $sessions = $query->limit($limit)->get();

            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => [
                    'count' => $sessions->count(),
                    'sessions' => $sessions->map(function ($session) {
                        return [
                            'id' => $session->id,
                            'user_query' => $session->user_query,
                            'llm_response' => $session->llm_response,
                            'model_used' => $session->model_used,
                            'tools_used' => $session->tools_used,
                            'user_rating' => $session->user_rating,
                            'metadata' => $session->metadata,
                        ];
                    }),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get high quality sessions', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '获取失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 更新个性化评分（三轨评分系统）
     * 
     * POST /api/internal/chat/update-personalization
     * 
     * 由DAML-RAG工作流在步骤12调用，自动计算并更新个性化感知评分
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePersonalization(Request $request): JsonResponse
    {
        try {
            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|string|max:36',
                'profile_utilization_rate' => 'required|numeric|min:0|max:100',
                'goal_alignment' => 'required|numeric|min:0|max:100',
                'uniqueness' => 'required|numeric|min:0|max:100',
                'dynamic_adjustment' => 'required|numeric|min:0|max:100',
                'personalization_grade' => 'required|string|in:S,A,B,C,D',
                'fewshot_eligible' => 'required|boolean',
                'overall_score' => 'nullable|numeric|min:0|max:5',
                'eligibility_reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'msg' => '验证失败',
                    'data' => [
                        'errors' => $validator->errors()
                    ]
                ], 422);
            }

            // 查找对话记录
            $chatSession = ChatSession::where('session_id', $request->session_id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$chatSession) {
                return response()->json([
                    'code' => 404,
                    'msg' => '对话记录不存在',
                    'data' => null
                ], 404);
            }

            // 更新个性化评分
            $chatSession->update([
                'profile_utilization_rate' => $request->profile_utilization_rate,
                'goal_alignment' => $request->goal_alignment,
                'uniqueness' => $request->uniqueness,
                'dynamic_adjustment' => $request->dynamic_adjustment,
                'personalization_grade' => $request->personalization_grade,
                'fewshot_eligible' => $request->fewshot_eligible,
                'overall_score' => $request->overall_score,
            ]);

            Log::info('Personalization scores updated', [
                'id' => $chatSession->id,
                'session_id' => $chatSession->session_id,
                'personalization_grade' => $request->personalization_grade,
                'fewshot_eligible' => $request->fewshot_eligible,
            ]);

            return response()->json([
                'code' => 200,
                'msg' => '个性化评分更新成功',
                'data' => [
                    'id' => $chatSession->id,
                    'session_id' => $chatSession->session_id,
                    'personalization_grade' => $chatSession->personalization_grade,
                    'fewshot_eligible' => $chatSession->fewshot_eligible,
                    'overall_score' => $chatSession->overall_score,
                    'updated_at' => $chatSession->updated_at->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update personalization scores', [
                'session_id' => $request->session_id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '更新失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 获取用户会话数量（用于冷启动判断）
     * 
     * GET /api/internal/chat/session-count/{userId}
     * 
     * @param int $userId
     * @return JsonResponse
     */
    public function getSessionCount(int $userId): JsonResponse
    {
        try {
            $count = ChatSession::where('user_id', $userId)->count();

            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => [
                    'user_id' => $userId,
                    'count' => $count,
                    'is_cold_start' => $count <= 3,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get session count', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '获取失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 检查会话Few-Shot资格
     * 
     * GET /api/internal/chat/fewshot-eligibility/{sessionId}
     * 
     * @param string $sessionId
     * @return JsonResponse
     */
    public function checkFewshotEligibility(string $sessionId): JsonResponse
    {
        try {
            $chatSession = ChatSession::where('session_id', $sessionId)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$chatSession) {
                return response()->json([
                    'code' => 404,
                    'msg' => '对话记录不存在',
                    'data' => null
                ], 404);
            }

            // 检查Few-Shot资格
            $eligible = $chatSession->fewshot_eligible ?? false;
            $reason = $this->getEligibilityReason($chatSession);

            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => [
                    'session_id' => $sessionId,
                    'eligible' => $eligible,
                    'reason' => $reason,
                    'personalization_grade' => $chatSession->personalization_grade,
                    'overall_score' => $chatSession->overall_score,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check fewshot eligibility', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '检查失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 获取资格判断原因
     * 
     * @param ChatSession $session
     * @return string
     */
    private function getEligibilityReason(ChatSession $session): string
    {
        if ($session->fewshot_eligible) {
            return '三轨评分均达标';
        }

        // 检查各项评分
        $reasons = [];

        if ($session->overall_score !== null && $session->overall_score < 4.0) {
            $reasons[] = sprintf('综合评分不足（%.2f < 4.0）', $session->overall_score);
        }

        if ($session->profile_utilization_rate !== null && $session->profile_utilization_rate < 60) {
            $reasons[] = sprintf('档案利用率不足（%.1f%% < 60%%）', $session->profile_utilization_rate);
        }

        if (empty($reasons)) {
            return '评分数据不完整';
        }

        return implode('；', $reasons);
    }

    /**
     * 保存对话话题
     * 
     * POST /api/internal/chat/save-topic
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function saveTopic(Request $request): JsonResponse
    {
        try {
            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'topic_id' => 'required|string|max:100',
                'user_id' => 'nullable|string|max:50',
                'name' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'msg' => '验证失败',
                    'data' => [
                        'errors' => $validator->errors()
                    ]
                ], 422);
            }

            $topicId = $request->input('topic_id');
            $userId = $request->input('user_id');
            $name = $request->input('name', '新对话');

            // 解析user_id（可能是guest或数字ID）
            $numericUserId = null;
            if ($userId && $userId !== 'guest' && is_numeric($userId)) {
                $numericUserId = (int) $userId;
            }

            // 查找或创建话题
            $topic = ChatTopic::where('id', $topicId)
                ->orWhere(function ($query) use ($topicId, $numericUserId) {
                    // 也尝试通过name匹配（兼容旧逻辑）
                    $query->where('name', $topicId);
                    if ($numericUserId) {
                        $query->where('user_id', $numericUserId);
                    }
                })
                ->first();

            if (!$topic) {
                // 创建新话题
                $topic = ChatTopic::create([
                    'user_id' => $numericUserId ?? 0,
                    'name' => $name,
                    'description' => "Topic ID: {$topicId}",
                    'message_count' => 0,
                ]);

                Log::info('Chat topic created', [
                    'id' => $topic->id,
                    'topic_id' => $topicId,
                    'user_id' => $userId,
                ]);
            }

            return response()->json([
                'code' => 200,
                'msg' => '话题保存成功',
                'data' => [
                    'id' => $topic->id,
                    'topic_id' => $topicId,
                    'name' => $topic->name,
                    'message_count' => $topic->message_count,
                    'created_at' => $topic->created_at->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save chat topic', [
                'topic_id' => $request->input('topic_id', 'unknown'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '保存失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 保存对话消息
     * 
     * POST /api/internal/chat/save-message
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function saveMessage(Request $request): JsonResponse
    {
        try {
            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'topic_id' => 'required|string|max:100',
                'user_id' => 'nullable|string|max:50',
                'role' => 'required|string|in:user,assistant',
                'content' => 'required|string|max:50000',
                'session_id' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'msg' => '验证失败',
                    'data' => [
                        'errors' => $validator->errors()
                    ]
                ], 422);
            }

            $topicId = $request->input('topic_id');
            $userId = $request->input('user_id');
            $role = $request->input('role');
            $content = $request->input('content');
            $sessionId = $request->input('session_id');

            // 解析user_id
            $numericUserId = null;
            if ($userId && $userId !== 'guest' && is_numeric($userId)) {
                $numericUserId = (int) $userId;
            }

            // 查找话题
            $topic = ChatTopic::where('id', $topicId)
                ->orWhere('name', $topicId)
                ->first();

            // 如果话题不存在，自动创建
            if (!$topic) {
                $topic = ChatTopic::create([
                    'user_id' => $numericUserId ?? 0,
                    'name' => '新对话',
                    'description' => "Topic ID: {$topicId}",
                    'message_count' => 0,
                ]);
            }

            // 创建消息记录
            $message = \App\Models\ChatMessage::create([
                'topic_id' => $topic->id,
                'user_id' => $numericUserId ?? 0,
                'role' => $role,
                'content' => $content,
                'client_id' => $sessionId, // 使用session_id作为client_id
                'metadata' => null,
            ]);

            // 更新话题的最后消息和计数
            $topic->increment('message_count');
            $topic->update([
                'last_message' => mb_substr($content, 0, 50),
                'last_message_at' => now(),
            ]);

            // 如果是assistant消息且有session_id，更新ChatSession的topic_id
            if ($role === 'assistant' && $sessionId) {
                ChatSession::where('session_id', $sessionId)
                    ->update(['topic_id' => $topic->id]);
            }

            Log::info('Chat message saved', [
                'message_id' => $message->id,
                'topic_id' => $topic->id,
                'role' => $role,
                'content_length' => strlen($content),
                'session_id' => $sessionId,
            ]);

            return response()->json([
                'code' => 200,
                'msg' => '消息保存成功',
                'data' => [
                    'message_id' => $message->id,
                    'topic_id' => $topic->id,
                    'role' => $role,
                    'message_count' => $topic->message_count,
                    'saved_at' => now()->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save chat message', [
                'topic_id' => $request->input('topic_id', 'unknown'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '保存失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 清空话题消息
     * 
     * DELETE /api/internal/chat/clear-topic/{topicId}
     * 
     * @param string $topicId
     * @return JsonResponse
     */
    public function clearTopic(string $topicId): JsonResponse
    {
        try {
            // 查找话题
            $topic = ChatTopic::where('id', $topicId)
                ->orWhere('name', $topicId)
                ->first();

            if (!$topic) {
                return response()->json([
                    'code' => 404,
                    'msg' => '话题不存在',
                    'data' => null
                ], 404);
            }

            // 删除关联的ChatSession记录
            $deletedCount = ChatSession::where('topic_id', $topic->id)->delete();

            // 重置话题计数
            $topic->update([
                'message_count' => 0,
                'last_message' => null,
                'last_message_at' => null,
            ]);

            Log::info('Chat topic cleared', [
                'topic_id' => $topic->id,
                'deleted_sessions' => $deletedCount,
            ]);

            return response()->json([
                'code' => 200,
                'msg' => '话题清空成功',
                'data' => [
                    'topic_id' => $topic->id,
                    'deleted_sessions' => $deletedCount,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear chat topic', [
                'topic_id' => $topicId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '清空失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 搜索相似对话（Few-Shot降级策略）
     * 
     * POST /api/internal/chat/search-similar
     * 
     * 当向量检索器不可用时，提供基于关键词的降级搜索
     * 
     * @requirements 4.6 - 检索器不可用时降级到后端API
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function searchSimilarConversations(Request $request): JsonResponse
    {
        try {
            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|max:1000',
                'user_id' => 'nullable|integer',
                'limit' => 'nullable|integer|min:1|max:50',
                'min_rating' => 'nullable|numeric|min:0|max:5',
                'only_fewshot_eligible' => 'nullable|boolean',
                'training_effect_filter' => 'nullable|string|in:excellent,good,fair,poor',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'msg' => '验证失败',
                    'data' => [
                        'errors' => $validator->errors()
                    ]
                ], 422);
            }

            $query = $request->input('query');
            $userId = $request->input('user_id');
            $limit = $request->input('limit', 10);
            $minRating = $request->input('min_rating', 4.0);
            $onlyFewshotEligible = $request->input('only_fewshot_eligible', true);
            $trainingEffectFilter = $request->input('training_effect_filter');

            // 构建查询
            $dbQuery = ChatSession::query()
                ->whereNotNull('user_query')
                ->whereNotNull('llm_response')
                ->where('user_rating', '>=', $minRating)
                ->orderBy('user_rating', 'desc')
                ->orderBy('overall_score', 'desc')
                ->orderBy('created_at', 'desc');

            // Few-Shot资格过滤
            if ($onlyFewshotEligible) {
                $dbQuery->where('fewshot_eligible', true);
            }

            // 训练效果过滤
            if ($trainingEffectFilter) {
                $dbQuery->where('training_effect', $trainingEffectFilter);
            }

            // 关键词搜索（简单的LIKE匹配）
            $keywords = $this->extractKeywords($query);
            if (!empty($keywords)) {
                $dbQuery->where(function ($q) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $q->orWhere('user_query', 'LIKE', "%{$keyword}%");
                    }
                });
            }

            // 执行查询
            $sessions = $dbQuery->limit($limit)->get();

            // 计算相似度分数（基于关键词匹配）
            $conversations = $sessions->map(function ($session) use ($keywords, $query) {
                $similarity = $this->calculateKeywordSimilarity($session->user_query, $keywords, $query);
                
                // 构建三轨评分详情
                $threeTrackScores = null;
                if ($session->ux_clarity !== null) {
                    $uxScores = [
                        $session->ux_clarity,
                        $session->ux_practicality,
                        $session->ux_detail,
                        $session->ux_friendliness,
                        $session->ux_satisfaction
                    ];
                    $uxAvg = collect($uxScores)->filter()->avg() ?? 0;
                    
                    $persScores = [
                        $session->profile_utilization_rate,
                        $session->goal_alignment,
                        $session->uniqueness,
                        $session->dynamic_adjustment
                    ];
                    // 转换为5分制
                    $persAvg = (collect($persScores)->filter()->avg() ?? 0) / 20;
                    
                    $threeTrackScores = [
                        'user_experience_avg' => round($uxAvg, 2),
                        'personalization_avg' => round($persAvg, 2),
                        'expert_avg' => null, // 专家评分需要从expert_reviews表获取
                        'safety_score' => null
                    ];
                }

                return [
                    'session_id' => $session->session_id,
                    'user_query' => $session->user_query,
                    'llm_response' => $session->llm_response,
                    'model_used' => $session->model_used,
                    'tools_used' => $session->tools_used,
                    'user_rating' => $session->user_rating,
                    'reward' => $session->user_rating, // 兼容字段
                    'quality_score' => $session->overall_score ?? $session->user_rating,
                    'overall_score' => $session->overall_score,
                    'similarity' => $similarity,
                    'created_at' => $session->created_at->toIso8601String(),
                    'metadata' => $session->metadata,
                    // 三轨评分相关
                    'three_track_scores' => $threeTrackScores,
                    'personalization_grade' => $session->personalization_grade,
                    'profile_utilization_rate' => $session->profile_utilization_rate,
                    'fewshot_eligible' => $session->fewshot_eligible,
                    // 训练效果和用户反馈
                    'training_effect' => $session->training_effect,
                    'user_feedback' => $session->user_feedback,
                    'feedback_text' => $session->user_feedback, // 兼容字段
                ];
            })->sortByDesc('similarity')->values();

            Log::info('Similar conversations search completed', [
                'query_length' => strlen($query),
                'keywords' => $keywords,
                'results_count' => $conversations->count(),
                'only_fewshot_eligible' => $onlyFewshotEligible,
            ]);

            return response()->json([
                'code' => 200,
                'msg' => '搜索成功',
                'data' => [
                    'conversations' => $conversations,
                    'total' => $conversations->count(),
                    'search_method' => 'keyword_fallback',
                    'filters_applied' => [
                        'min_rating' => $minRating,
                        'only_fewshot_eligible' => $onlyFewshotEligible,
                        'training_effect_filter' => $trainingEffectFilter,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to search similar conversations', [
                'query' => $request->input('query', 'unknown'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '搜索失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 从查询中提取关键词
     * 
     * @param string $query
     * @return array
     */
    private function extractKeywords(string $query): array
    {
        // 移除常见停用词
        $stopWords = ['的', '了', '是', '在', '我', '有', '和', '就', '不', '人', '都', '一', '一个', '上', '也', '很', '到', '说', '要', '去', '你', '会', '着', '没有', '看', '好', '自己', '这', '那', '什么', '怎么', '如何', '请', '帮', '帮我', '想', '能', '可以', '吗', '呢'];
        
        // 分词（简单按空格和标点分割）
        $words = preg_split('/[\s,，。！？、；：""\'\'（）\[\]【】]+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
        
        // 过滤停用词和短词
        $keywords = array_filter($words, function ($word) use ($stopWords) {
            return mb_strlen($word) >= 2 && !in_array($word, $stopWords);
        });
        
        // 限制关键词数量
        return array_slice(array_values($keywords), 0, 10);
    }

    /**
     * 计算关键词相似度
     * 
     * @param string $text
     * @param array $keywords
     * @param string $originalQuery
     * @return float
     */
    private function calculateKeywordSimilarity(string $text, array $keywords, string $originalQuery): float
    {
        if (empty($keywords)) {
            return 0.5; // 默认相似度
        }

        $matchCount = 0;
        $textLower = mb_strtolower($text);
        
        foreach ($keywords as $keyword) {
            if (mb_strpos($textLower, mb_strtolower($keyword)) !== false) {
                $matchCount++;
            }
        }

        // 基础相似度：关键词匹配率
        $baseSimilarity = $matchCount / count($keywords);
        
        // 长度相似度加成
        $lengthRatio = min(mb_strlen($text), mb_strlen($originalQuery)) / max(mb_strlen($text), mb_strlen($originalQuery), 1);
        
        // 综合相似度
        $similarity = ($baseSimilarity * 0.8) + ($lengthRatio * 0.2);
        
        return round(min(1.0, $similarity), 3);
    }
}
