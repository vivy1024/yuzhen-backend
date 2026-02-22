<?php

namespace App\Modules\Chat\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * ChatSessionController — 会话管理 API
 *
 * 从 InternalChatController 拆分，负责会话 CRUD 和评分相关操作。
 */
class ChatSessionController extends BaseController
{
    public function saveChatSession(Request $request): JsonResponse
    {
        try {
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
                return $this->fail('验证失败', 422, ['errors' => $validator->errors()]);
            }

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

            return $this->success([
                'id' => $chatSession->id,
                'session_id' => $chatSession->session_id,
                'created_at' => $chatSession->created_at->toIso8601String(),
            ], '保存成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '保存对话会话');
        }
    }

    public function updateFeedback(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|string|max:36',
                'reward' => 'required|numeric|min:1|max:5',
                'feedback_text' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->fail('验证失败', 422, ['errors' => $validator->errors()]);
            }

            $chatSession = ChatSession::where('session_id', $request->session_id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$chatSession) {
                return $this->fail('对话记录不存在', 404);
            }

            $chatSession->update([
                'user_rating' => (int) $request->reward,
                'user_feedback' => $request->feedback_text,
            ]);

            Log::info('Chat feedback updated', [
                'id' => $chatSession->id,
                'session_id' => $chatSession->session_id,
                'user_rating' => $chatSession->user_rating,
            ]);

            return $this->success([
                'id' => $chatSession->id,
                'session_id' => $chatSession->session_id,
                'user_rating' => $chatSession->user_rating,
                'is_high_quality' => $chatSession->isHighQuality(),
                'qdrant_point_id' => $chatSession->qdrant_point_id,
                'updated_at' => $chatSession->updated_at->toIso8601String(),
            ], '反馈更新成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '更新对话反馈');
        }
    }

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

            return $this->success([
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
            ], '获取成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取对话历史');
        }
    }

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

            return $this->success([
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
            ], '获取成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取高质量对话');
        }
    }

    public function updatePersonalization(Request $request): JsonResponse
    {
        try {
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
                return $this->fail('验证失败', 422, ['errors' => $validator->errors()]);
            }

            $chatSession = ChatSession::where('session_id', $request->session_id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$chatSession) {
                return $this->fail('对话记录不存在', 404);
            }

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

            return $this->success([
                'id' => $chatSession->id,
                'session_id' => $chatSession->session_id,
                'personalization_grade' => $chatSession->personalization_grade,
                'fewshot_eligible' => $chatSession->fewshot_eligible,
                'overall_score' => $chatSession->overall_score,
                'updated_at' => $chatSession->updated_at->toIso8601String(),
            ], '个性化评分更新成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '更新个性化评分');
        }
    }

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

    private function getEligibilityReason(ChatSession $session): string
    {
        if ($session->fewshot_eligible) {
            return '三轨评分均达标';
        }

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
}
