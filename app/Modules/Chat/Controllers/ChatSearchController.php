<?php

namespace App\Modules\Chat\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * ChatSearchController — 对话搜索 API
 *
 * 从 InternalChatController 拆分，负责相似对话搜索（Few-Shot降级策略）。
 */
class ChatSearchController extends BaseController
{
    public function searchSimilarConversations(Request $request): JsonResponse
    {
        try {
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
                    'code' => 422, 'msg' => '验证失败',
                    'data' => ['errors' => $validator->errors()]
                ], 422);
            }

            $query = $request->input('query');
            $limit = $request->input('limit', 10);
            $minRating = $request->input('min_rating', 4.0);
            $onlyFewshotEligible = $request->input('only_fewshot_eligible', true);
            $trainingEffectFilter = $request->input('training_effect_filter');

            $dbQuery = ChatSession::query()
                ->whereNotNull('user_query')
                ->whereNotNull('llm_response')
                ->where('user_rating', '>=', $minRating)
                ->orderBy('user_rating', 'desc')
                ->orderBy('overall_score', 'desc')
                ->orderBy('created_at', 'desc');

            if ($onlyFewshotEligible) {
                $dbQuery->where('fewshot_eligible', true);
            }

            if ($trainingEffectFilter) {
                $dbQuery->where('training_effect', $trainingEffectFilter);
            }

            $keywords = $this->extractKeywords($query);
            if (!empty($keywords)) {
                $dbQuery->where(function ($q) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $q->orWhere('user_query', 'LIKE', "%{$keyword}%");
                    }
                });
            }

            $sessions = $dbQuery->limit($limit)->get();

            $conversations = $sessions->map(function ($session) use ($keywords, $query) {
                $similarity = $this->calculateKeywordSimilarity($session->user_query, $keywords, $query);

                $threeTrackScores = null;
                if ($session->ux_clarity !== null) {
                    $uxScores = [
                        $session->ux_clarity, $session->ux_practicality,
                        $session->ux_detail, $session->ux_friendliness, $session->ux_satisfaction
                    ];
                    $uxAvg = collect($uxScores)->filter()->avg() ?? 0;

                    $persScores = [
                        $session->profile_utilization_rate, $session->goal_alignment,
                        $session->uniqueness, $session->dynamic_adjustment
                    ];
                    $persAvg = (collect($persScores)->filter()->avg() ?? 0) / 20;

                    $threeTrackScores = [
                        'user_experience_avg' => round($uxAvg, 2),
                        'personalization_avg' => round($persAvg, 2),
                        'expert_avg' => null,
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
                    'reward' => $session->user_rating,
                    'quality_score' => $session->overall_score ?? $session->user_rating,
                    'overall_score' => $session->overall_score,
                    'similarity' => $similarity,
                    'created_at' => $session->created_at->toIso8601String(),
                    'metadata' => $session->metadata,
                    'three_track_scores' => $threeTrackScores,
                    'personalization_grade' => $session->personalization_grade,
                    'profile_utilization_rate' => $session->profile_utilization_rate,
                    'fewshot_eligible' => $session->fewshot_eligible,
                    'training_effect' => $session->training_effect,
                    'user_feedback' => $session->user_feedback,
                    'feedback_text' => $session->user_feedback,
                ];
            })->sortByDesc('similarity')->values();

            Log::info('Similar conversations search completed', [
                'query_length' => strlen($query), 'keywords' => $keywords,
                'results_count' => $conversations->count(),
                'only_fewshot_eligible' => $onlyFewshotEligible,
            ]);

            return response()->json([
                'code' => 200, 'msg' => '搜索成功',
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
                'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500, 'msg' => '搜索失败: ' . $e->getMessage(), 'data' => null
            ], 500);
        }
    }

    private function extractKeywords(string $query): array
    {
        $stopWords = ['的', '了', '是', '在', '我', '有', '和', '就', '不', '人', '都', '一', '一个', '上', '也', '很', '到', '说', '要', '去', '你', '会', '着', '没有', '看', '好', '自己', '这', '那', '什么', '怎么', '如何', '请', '帮', '帮我', '想', '能', '可以', '吗', '呢'];

        $words = preg_split('/[\s,，。！？、；：""\'\'（）\[\]【】]+/u', $query, -1, PREG_SPLIT_NO_EMPTY);

        $keywords = array_filter($words, function ($word) use ($stopWords) {
            return mb_strlen($word) >= 2 && !in_array($word, $stopWords);
        });

        return array_slice(array_values($keywords), 0, 10);
    }

    private function calculateKeywordSimilarity(string $text, array $keywords, string $originalQuery): float
    {
        if (empty($keywords)) {
            return 0.5;
        }

        $matchCount = 0;
        $textLower = mb_strtolower($text);

        foreach ($keywords as $keyword) {
            if (mb_strpos($textLower, mb_strtolower($keyword)) !== false) {
                $matchCount++;
            }
        }

        $baseSimilarity = $matchCount / count($keywords);
        $lengthRatio = min(mb_strlen($text), mb_strlen($originalQuery)) / max(mb_strlen($text), mb_strlen($originalQuery), 1);
        $similarity = ($baseSimilarity * 0.8) + ($lengthRatio * 0.2);

        return round(min(1.0, $similarity), 3);
    }
}
