<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\ExpertReview;
use App\Services\PersonalizationScoreService;
use App\Services\FewShotEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * QualityRatingController - 三轨评分系统API
 * 
 * 实现三轨评分体系：
 * 1. 用户体验评分 (5维度)
 * 2. 个性化感知评分 (4维度，自动计算)
 * 3. 专家专业评分 (6维度)
 * 
 * Few-Shot准入规则：
 * - 三轨高分（≥4.0）
 * - 安全性一票否决（<3）
 * - 冷启动期保护（前3条对话）
 * 
 * @version 2.0.0
 * @date 2025-12-31
 */
class QualityRatingController extends Controller
{
    /**
     * 个性化评分服务
     */
    private PersonalizationScoreService $personalizationService;

    /**
     * Few-Shot准入服务
     */
    private FewShotEligibilityService $fewShotService;

    public function __construct(
        PersonalizationScoreService $personalizationService,
        FewShotEligibilityService $fewShotService
    ) {
        $this->personalizationService = $personalizationService;
        $this->fewShotService = $fewShotService;
    }

    /**
     * 提交三轨评分
     * 
     * POST /api/v2/quality/rating
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function submitRating(Request $request): JsonResponse
    {
        try {
            // 参数验证
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|string',
                // 用户体验评分（5维度）
                'user_experience' => 'required|array',
                'user_experience.clarity' => 'required|integer|min:1|max:5',
                'user_experience.practicality' => 'required|integer|min:1|max:5',
                'user_experience.detail' => 'required|integer|min:1|max:5',
                'user_experience.friendliness' => 'required|integer|min:1|max:5',
                'user_experience.satisfaction' => 'required|integer|min:1|max:5',
                // 用户反馈文本（可选）
                'feedback_text' => 'sometimes|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 400,
                    'msg' => '参数验证失败',
                    'data' => $validator->errors()
                ], 400);
            }

            // 获取会话
            $session = ChatSession::where('session_id', $request->input('session_id'))->first();
            if (!$session) {
                return response()->json([
                    'code' => 404,
                    'msg' => '会话不存在'
                ], 404);
            }

            // 验证用户权限（只能评价自己的会话）
            $userId = auth()->id();
            if ($session->user_id && $session->user_id !== $userId) {
                return response()->json([
                    'code' => 403,
                    'msg' => '无权限为此会话评分'
                ], 403);
            }

            // 更新用户体验评分
            $ux = $request->input('user_experience');
            $session->ux_clarity = $ux['clarity'];
            $session->ux_practicality = $ux['practicality'];
            $session->ux_detail = $ux['detail'];
            $session->ux_friendliness = $ux['friendliness'];
            $session->ux_satisfaction = $ux['satisfaction'];

            // 保存用户反馈
            if ($request->has('feedback_text')) {
                $session->user_feedback = $request->input('feedback_text');
            }

            // 计算用户体验平均分作为user_rating
            $uxAvg = ($ux['clarity'] + $ux['practicality'] + $ux['detail'] + 
                     $ux['friendliness'] + $ux['satisfaction']) / 5;
            $session->user_rating = round($uxAvg);

            // 自动计算个性化感知评分（使用服务）
            $personalization = $this->personalizationService->calculateAllScores($session);
            $session->profile_utilization_rate = $personalization['profile_utilization_rate'];
            $session->goal_alignment = $personalization['goal_alignment'];
            $session->uniqueness = $personalization['uniqueness'];
            $session->dynamic_adjustment = $personalization['dynamic_adjustment'];

            // 计算个性化等级（使用服务）
            $session->personalization_grade = $this->personalizationService->calculateGrade(
                $personalization['profile_utilization_rate']
            );

            // 更新综合评分和Few-Shot资格（使用服务）
            $session->overall_score = $session->calculateOverallScore();
            $eligibilityResult = $this->fewShotService->checkEligibility($session);
            $session->fewshot_eligible = $eligibilityResult['eligible'];

            $session->save();

            // 构建响应数据
            $responseData = [
                'session_id' => $session->session_id,
                'user_experience' => $session->getUserExperienceScores(),
                'personalization' => $session->getPersonalizationScores(),
                'personalization_grade' => $session->personalization_grade,
                'overall_score' => $session->overall_score,
                'fewshot_eligible' => $session->fewshot_eligible,
                'expert_review' => null,
            ];

            // 如果有专家评审，也返回
            $expertReview = $session->expertReviews()->first();
            if ($expertReview) {
                $responseData['expert_review'] = $expertReview->getAllScores();
            }

            return response()->json([
                'code' => 200,
                'msg' => '评分提交成功',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error('三轨评分提交失败: ' . $e->getMessage(), [
                'session_id' => $request->input('session_id'),
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '评分提交失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取会话评分
     * 
     * GET /api/v2/quality/rating/{session_id}
     * 
     * @param string $sessionId
     * @return JsonResponse
     */
    public function getRating(string $sessionId): JsonResponse
    {
        try {
            $session = ChatSession::where('session_id', $sessionId)->first();
            if (!$session) {
                return response()->json([
                    'code' => 404,
                    'msg' => '会话不存在'
                ], 404);
            }

            // 验证用户权限
            $userId = auth()->id();
            $user = auth()->user();
            
            // 管理员可以查看所有，普通用户只能查看自己的
            if (!$user->isAdmin() && $session->user_id && $session->user_id !== $userId) {
                return response()->json([
                    'code' => 403,
                    'msg' => '无权限查看此会话评分'
                ], 403);
            }

            // 构建响应数据
            $responseData = [
                'session_id' => $session->session_id,
                'user_experience' => $session->getUserExperienceScores(),
                'personalization' => $session->getPersonalizationScores(),
                'personalization_grade' => $session->personalization_grade,
                'overall_score' => $session->overall_score,
                'fewshot_eligible' => $session->fewshot_eligible,
                'user_feedback' => $session->user_feedback,
                'expert_review' => null,
                'created_at' => $session->created_at?->toIso8601String(),
                'updated_at' => $session->updated_at?->toIso8601String(),
            ];

            // 获取专家评审
            $expertReview = $session->expertReviews()->first();
            if ($expertReview) {
                $responseData['expert_review'] = $expertReview->getSummary();
            }

            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error('获取评分失败: ' . $e->getMessage(), [
                'session_id' => $sessionId
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '获取评分失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提交专家评审
     * 
     * POST /api/v2/quality/rating/{session_id}/expert
     * 
     * @param Request $request
     * @param string $sessionId
     * @return JsonResponse
     */
    public function submitExpertReview(Request $request, string $sessionId): JsonResponse
    {
        try {
            // 验证用户是否为专家/管理员
            $user = auth()->user();
            if (!$user->isAdmin() && !$user->isExpert()) {
                return response()->json([
                    'code' => 403,
                    'msg' => '只有专家或管理员可以提交专家评审'
                ], 403);
            }

            // 参数验证
            $validator = Validator::make($request->all(), [
                'accuracy' => 'required|integer|min:1|max:5',
                'scientific' => 'required|integer|min:1|max:5',
                'safety' => 'required|integer|min:1|max:5',
                'completeness' => 'required|integer|min:1|max:5',
                'practicality' => 'required|integer|min:1|max:5',
                'personalization' => 'required|integer|min:1|max:5',
                'comments' => 'sometimes|string|max:2000',
                'improvement_suggestions' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 400,
                    'msg' => '参数验证失败',
                    'data' => $validator->errors()
                ], 400);
            }

            // 获取会话
            $session = ChatSession::where('session_id', $sessionId)->first();
            if (!$session) {
                return response()->json([
                    'code' => 404,
                    'msg' => '会话不存在'
                ], 404);
            }

            // 创建或更新专家评审
            $expertReview = ExpertReview::updateOrCreate(
                [
                    'chat_session_id' => $session->id,
                    'expert_id' => $user->id,
                ],
                [
                    'accuracy' => $request->input('accuracy'),
                    'scientific' => $request->input('scientific'),
                    'safety' => $request->input('safety'),
                    'completeness' => $request->input('completeness'),
                    'practicality' => $request->input('practicality'),
                    'personalization' => $request->input('personalization'),
                    'comments' => $request->input('comments'),
                    'improvement_suggestions' => $request->input('improvement_suggestions'),
                ]
            );

            // 更新会话的综合评分和Few-Shot资格（使用服务）
            $session->overall_score = $session->calculateOverallScore();
            $eligibilityResult = $this->fewShotService->checkEligibility($session);
            $session->fewshot_eligible = $eligibilityResult['eligible'];
            $session->save();

            return response()->json([
                'code' => 200,
                'msg' => '专家评审提交成功',
                'data' => [
                    'session_id' => $sessionId,
                    'expert_review' => $expertReview->getSummary(),
                    'overall_score' => $session->overall_score,
                    'fewshot_eligible' => $session->fewshot_eligible,
                    'eligibility_details' => $eligibilityResult,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('专家评审提交失败: ' . $e->getMessage(), [
                'session_id' => $sessionId,
                'expert_id' => auth()->id()
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '专家评审提交失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 检查会话Few-Shot资格（带详细原因）
     * 
     * GET /api/v2/quality/rating/{session_id}/eligibility
     * 
     * @param string $sessionId
     * @return JsonResponse
     */
    public function checkEligibility(string $sessionId): JsonResponse
    {
        try {
            $session = ChatSession::where('session_id', $sessionId)->first();
            if (!$session) {
                return response()->json([
                    'code' => 404,
                    'msg' => '会话不存在'
                ], 404);
            }

            // 验证用户权限
            $userId = auth()->id();
            $user = auth()->user();
            
            if (!$user->isAdmin() && $session->user_id && $session->user_id !== $userId) {
                return response()->json([
                    'code' => 403,
                    'msg' => '无权限查看此会话'
                ], 403);
            }

            // 检查Few-Shot资格
            $result = $this->fewShotService->checkEligibility($session);

            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => [
                    'session_id' => $sessionId,
                    'eligible' => $result['eligible'],
                    'reason' => $result['reason'],
                    'details' => $result['details'],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('检查Few-Shot资格失败: ' . $e->getMessage(), [
                'session_id' => $sessionId
            ]);

            return response()->json([
                'code' => 500,
                'msg' => '检查失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取用户冷启动状态
     * 
     * GET /api/v2/quality/cold-start-status
     * 
     * @return JsonResponse
     */
    public function getColdStartStatus(): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            // 获取用户会话数量
            $sessionCount = ChatSession::where('user_id', $userId)->count();
            $coldStartCount = 3; // 冷启动期对话数量
            
            $isColdStart = $sessionCount <= $coldStartCount;
            $remainingSessions = max(0, $coldStartCount - $sessionCount);
            
            // 获取用户已评分的会话数量
            $ratedSessionCount = ChatSession::where('user_id', $userId)
                ->whereNotNull('ux_clarity')
                ->count();
            
            // 获取用户Few-Shot合格的会话数量
            $eligibleSessionCount = ChatSession::where('user_id', $userId)
                ->where('fewshot_eligible', true)
                ->count();

            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => [
                    'is_cold_start' => $isColdStart,
                    'session_count' => $sessionCount,
                    'cold_start_threshold' => $coldStartCount,
                    'remaining_cold_start_sessions' => $remainingSessions,
                    'rated_session_count' => $ratedSessionCount,
                    'eligible_session_count' => $eligibleSessionCount,
                    'cold_start_benefits' => $isColdStart ? [
                        '降低评分门槛（3.5分即可进入Few-Shot池）',
                        '帮助系统快速学习您的偏好',
                        '提供更个性化的建议',
                    ] : [],
                    'message' => $isColdStart 
                        ? sprintf('您还有 %d 次冷启动期对话机会，评分门槛已降低', $remainingSessions)
                        : '您已完成冷启动期，现在使用标准评分门槛',
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('获取冷启动状态失败: ' . $e->getMessage());

            return response()->json([
                'code' => 500,
                'msg' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取Few-Shot池统计
     * 
     * GET /api/v2/quality/fewshot-pool-stats
     * 
     * @return JsonResponse
     */
    public function getFewShotPoolStats(): JsonResponse
    {
        try {
            // 只允许管理员访问
            $user = auth()->user();
            if (!$user->isAdmin()) {
                return response()->json([
                    'code' => 403,
                    'msg' => '权限不足'
                ], 403);
            }

            $stats = $this->fewShotService->getPoolStats();

            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('获取Few-Shot池统计失败: ' . $e->getMessage());

            return response()->json([
                'code' => 500,
                'msg' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * 获取Few-Shot合格的会话列表
     * 
     * GET /api/v2/quality/fewshot-eligible
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getFewShotEligible(Request $request): JsonResponse
    {
        try {
            // 只允许管理员访问
            $user = auth()->user();
            if (!$user->isAdmin()) {
                return response()->json([
                    'code' => 403,
                    'msg' => '权限不足'
                ], 403);
            }

            $limit = $request->input('limit', 20);
            $page = $request->input('page', 1);

            $sessions = ChatSession::fewShotEligible()
                ->orderBy('overall_score', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            $items = $sessions->map(function ($session) {
                return [
                    'session_id' => $session->session_id,
                    'user_query' => $session->user_query,
                    'llm_response' => $session->llm_response,
                    'tools_used' => $session->tools_used,
                    'user_experience' => $session->getUserExperienceScores(),
                    'personalization' => $session->getPersonalizationScores(),
                    'personalization_grade' => $session->personalization_grade,
                    'overall_score' => $session->overall_score,
                    'created_at' => $session->created_at?->toIso8601String(),
                ];
            });

            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => [
                    'items' => $items,
                    'total' => $sessions->total(),
                    'page' => $page,
                    'limit' => $limit,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('获取Few-Shot合格会话失败: ' . $e->getMessage());

            return response()->json([
                'code' => 500,
                'msg' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取评分统计
     * 
     * GET /api/v2/quality/stats
     * 
     * @return JsonResponse
     */
    public function getStats(): JsonResponse
    {
        try {
            // 只允许管理员访问
            $user = auth()->user();
            if (!$user->isAdmin()) {
                return response()->json([
                    'code' => 403,
                    'msg' => '权限不足'
                ], 403);
            }

            // 总体统计
            $totalSessions = ChatSession::count();
            $ratedSessions = ChatSession::whereNotNull('ux_clarity')->count();
            $fewshotEligible = ChatSession::where('fewshot_eligible', true)->count();

            // 个性化等级分布
            $gradeDistribution = ChatSession::selectRaw('personalization_grade, COUNT(*) as count')
                ->whereNotNull('personalization_grade')
                ->groupBy('personalization_grade')
                ->pluck('count', 'personalization_grade')
                ->toArray();

            // 平均分统计
            $avgScores = [
                'ux_clarity' => ChatSession::whereNotNull('ux_clarity')->avg('ux_clarity'),
                'ux_practicality' => ChatSession::whereNotNull('ux_practicality')->avg('ux_practicality'),
                'ux_detail' => ChatSession::whereNotNull('ux_detail')->avg('ux_detail'),
                'ux_friendliness' => ChatSession::whereNotNull('ux_friendliness')->avg('ux_friendliness'),
                'ux_satisfaction' => ChatSession::whereNotNull('ux_satisfaction')->avg('ux_satisfaction'),
                'profile_utilization_rate' => ChatSession::whereNotNull('profile_utilization_rate')->avg('profile_utilization_rate'),
                'overall_score' => ChatSession::whereNotNull('overall_score')->avg('overall_score'),
            ];

            // 专家评审统计
            $expertReviewCount = ExpertReview::count();
            $unsafeCount = ExpertReview::where('safety', '<', 3)->count();

            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => [
                    'total_sessions' => $totalSessions,
                    'rated_sessions' => $ratedSessions,
                    'fewshot_eligible' => $fewshotEligible,
                    'fewshot_rate' => $totalSessions > 0 ? round($fewshotEligible / $totalSessions * 100, 2) : 0,
                    'grade_distribution' => $gradeDistribution,
                    'avg_scores' => array_map(fn($v) => $v ? round($v, 2) : null, $avgScores),
                    'expert_review_count' => $expertReviewCount,
                    'unsafe_count' => $unsafeCount,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('获取评分统计失败: ' . $e->getMessage());

            return response()->json([
                'code' => 500,
                'msg' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 计算个性化感知评分
     * 
     * 基于会话的元数据自动计算：
     * - 档案利用率：检查是否使用了用户档案信息
     * - 目标对齐度：检查是否针对用户目标
     * - 独特性：检查回复是否个性化
     * - 动态调整：检查是否根据用户反馈调整
     * 
     * @param ChatSession $session
     * @return array
     */
    private function calculatePersonalizationScores(ChatSession $session): array
    {
        $metadata = $session->metadata ?? [];
        $toolsUsed = $session->tools_used ?? [];

        // 档案利用率计算（0-100%）
        $profileUtilization = $this->calculateProfileUtilization($metadata, $toolsUsed);

        // 目标对齐度计算（0-100%）
        $goalAlignment = $this->calculateGoalAlignment($metadata, $session->user_query);

        // 独特性计算（0-100%）
        $uniqueness = $this->calculateUniqueness($session);

        // 动态调整计算（0-100%）
        $dynamicAdjustment = $this->calculateDynamicAdjustment($session);

        return [
            'profile_utilization_rate' => $profileUtilization,
            'goal_alignment' => $goalAlignment,
            'uniqueness' => $uniqueness,
            'dynamic_adjustment' => $dynamicAdjustment,
        ];
    }

    /**
     * 计算档案利用率
     * 
     * @param array $metadata
     * @param array $toolsUsed
     * @return float
     */
    private function calculateProfileUtilization(array $metadata, array $toolsUsed): float
    {
        $score = 0;
        $maxScore = 100;

        // 检查是否使用了用户档案工具
        $profileTools = ['get_user_profile', 'user_profile_integrator'];
        foreach ($profileTools as $tool) {
            if (in_array($tool, $toolsUsed)) {
                $score += 20;
            }
        }

        // 检查元数据中的档案使用情况
        $profileFields = [
            'user_level' => 15,           // 用户等级
            'user_goals' => 15,           // 用户目标
            'user_injuries' => 15,        // 用户伤病
            'user_equipment' => 15,       // 用户器械
            'user_preferences' => 10,     // 用户偏好
            'user_history' => 10,         // 用户历史
        ];

        foreach ($profileFields as $field => $points) {
            if (isset($metadata[$field]) && !empty($metadata[$field])) {
                $score += $points;
            }
        }

        return min($score, $maxScore);
    }

    /**
     * 计算目标对齐度
     * 
     * @param array $metadata
     * @param string|null $userQuery
     * @return float
     */
    private function calculateGoalAlignment(array $metadata, ?string $userQuery): float
    {
        $score = 50; // 基础分

        // 检查是否识别了用户目标
        if (isset($metadata['detected_goal']) && !empty($metadata['detected_goal'])) {
            $score += 20;
        }

        // 检查是否针对目标给出建议
        if (isset($metadata['goal_specific_advice']) && $metadata['goal_specific_advice']) {
            $score += 20;
        }

        // 检查是否使用了目标相关工具
        $goalTools = ['training_goal_recommender', 'professional_program_designer'];
        $toolsUsed = $metadata['tools_used'] ?? [];
        foreach ($goalTools as $tool) {
            if (in_array($tool, $toolsUsed)) {
                $score += 5;
            }
        }

        return min($score, 100);
    }

    /**
     * 计算独特性
     * 
     * @param ChatSession $session
     * @return float
     */
    private function calculateUniqueness(ChatSession $session): float
    {
        $score = 50; // 基础分

        // 检查是否使用了个性化工具
        $toolsUsed = $session->tools_used ?? [];
        $personalizationTools = [
            'intelligent_exercise_selector',
            'contraindications_checker',
            'injury_risk_assessor',
            'safe_exercise_modifier',
        ];

        foreach ($personalizationTools as $tool) {
            if (in_array($tool, $toolsUsed)) {
                $score += 10;
            }
        }

        // 检查回复长度（更长的回复通常更个性化）
        $responseLength = strlen($session->llm_response ?? '');
        if ($responseLength > 500) {
            $score += 10;
        }

        return min($score, 100);
    }

    /**
     * 计算动态调整
     * 
     * @param ChatSession $session
     * @return float
     */
    private function calculateDynamicAdjustment(ChatSession $session): float
    {
        $score = 50; // 基础分

        // 检查是否是多轮对话
        $conversationCount = ChatSession::where('session_id', $session->session_id)->count();
        if ($conversationCount > 1) {
            $score += 20;
        }

        // 检查是否有上下文引用
        $metadata = $session->metadata ?? [];
        if (isset($metadata['context_used']) && $metadata['context_used']) {
            $score += 15;
        }

        // 检查是否根据反馈调整
        if (isset($metadata['feedback_incorporated']) && $metadata['feedback_incorporated']) {
            $score += 15;
        }

        return min($score, 100);
    }

    /**
     * 检查Few-Shot资格（含冷启动保护）
     * 
     * @param ChatSession $session
     * @return bool
     */
    private function checkFewShotEligibilityWithColdStart(ChatSession $session): bool
    {
        // 冷启动期保护：前3条对话特殊处理
        if ($session->user_id) {
            $userSessionCount = ChatSession::where('user_id', $session->user_id)->count();
            if ($userSessionCount <= 3) {
                // 冷启动期：降低门槛，只要用户体验评分>=3.5即可
                $uxScores = array_filter([
                    $session->ux_clarity,
                    $session->ux_practicality,
                    $session->ux_detail,
                    $session->ux_friendliness,
                    $session->ux_satisfaction,
                ], fn($v) => $v !== null);

                if (empty($uxScores)) {
                    return false;
                }

                $uxAvg = array_sum($uxScores) / count($uxScores);
                return $uxAvg >= 3.5;
            }
        }

        // 正常情况：使用标准的三轨评分检查
        return $session->checkFewShotEligibility();
    }
}
