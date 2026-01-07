<?php

/**
 * Chat Session Rating Module - 会话评分管理
 *
 * 基于三轨评分系统的完整实现：
 * - 用户体验评分 (UX)
 * - 个性化感知评分 (Personalization)
 * - 专家专业评分 (Expert)
 *
 * 只有通过质量门槛的对话才进入向量库和Few-Shot学习
 *
 * @author BUILD_BODY Team
 * @version 1.0.0
 * @created 2025-12-04
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\ChatSession;

/**
 * 提交多维度评分
 *
 * POST /api/chat/sessions/{session_id}/rate
 */
Route::post('/sessions/{session_id}/rate', function (Request $request, $session_id) {
    try {
        // 参数验证
        $validator = Validator::make($request->all(), [
            'user_experience.understandability' => 'required|integer|min:1|max:5',
            'user_experience.practicality' => 'required|integer|min:1|max:5',
            'user_experience.completeness' => 'required|integer|min:1|max:5',
            'user_experience.friendliness' => 'required|integer|min:1|max:5',
            'user_experience.overall_satisfaction' => 'required|integer|min:1|max:5',
            'personalization.profile_match' => 'required|integer|min:1|max:5',
            'personalization.goal_alignment' => 'required|integer|min:1|max:5',
            'personalization.uniqueness' => 'required|integer|min:1|max:5',
            'personalization.adaptability' => 'required|integer|min:1|max:5',
            'expert.professional_accuracy' => 'sometimes|integer|min:1|max:5',
            'expert.scientific_validity' => 'sometimes|integer|min:1|max:5',
            'expert.safety_assessment' => 'sometimes|integer|min:1|max:5',
            'expert.completeness' => 'sometimes|integer|min:1|max:5',
            'expert.feasibility' => 'sometimes|integer|min:1|max:5',
            'expert.personalization_quality' => 'sometimes|integer|min:1|max:5',
            'feedback_text' => 'sometimes|string|max:1000',
            'should_store_vector' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 400,
                'msg' => '参数验证失败',
                'data' => $validator->errors()
            ], 400);
        }

        // 获取会话信息
        $session = ChatSession::where('session_id', $session_id)->first();
        if (!$session) {
            return response()->json([
                'code' => 404,
                'msg' => '会话不存在'
            ], 404);
        }

        // 验证用户权限
        $userId = auth()->id();
        if ($session->user_id !== $userId) {
            return response()->json([
                'code' => 403,
                'msg' => '无权限为此会话评分'
            ], 403);
        }

        // 计算三轨评分
        $ratingResult = calculateTripleTrackRating(
            $request->input('user_experience'),
            $request->input('personalization'),
            $request->input('expert')
        );

        // 更新会话评分
        $session->update([
            'user_rating_vector' => json_encode($request->input('user_experience')),
            'personalization_rating_vector' => json_encode($request->input('personalization')),
            'expert_rating_vector' => $request->has('expert') ? json_encode($request->input('expert')) : null,

            // 计算得分
            'user_ux_score' => $ratingResult['user_ux_score'],
            'personalization_score' => $ratingResult['personalization_score'],
            'expert_qa_score' => $ratingResult['expert_qa_score'],

            // 综合质量
            'overall_quality' => $ratingResult['overall_quality'],
            'quality_grade' => $ratingResult['quality_grade'],

            // Few-Shot资格
            'fewshot_eligible' => $ratingResult['fewshot_eligible'],
            'is_safe' => $ratingResult['is_safe'],

            // 反馈信息
            'feedback_detailed' => $request->input('feedback_text'),
            'user_feedback_tags' => json_encode(extractFeedbackTags($request->input('feedback_text'))),

            'updated_at' => now()
        ]);

        // 用户选择存储到向量库且质量达标
        $vectorStored = false;
        if ($request->input('should_store_vector', false) && $ratingResult['fewshot_eligible']) {
            $vectorStored = storeToVectorLibrary($session, $ratingResult);
        }

        // 生成质量报告
        $qualityReport = generateQualityReport($session, $ratingResult);

        return response()->json([
            'code' => 200,
            'msg' => '评分成功',
            'data' => [
                'session' => $session->fresh(),
                'quality_report' => $qualityReport,
                'vector_stored' => $vectorStored
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('会话评分失败: ' . $e->getMessage(), [
            'session_id' => $session_id,
            'user_id' => auth()->id()
        ]);

        return response()->json([
            'code' => 500,
            'msg' => '评分失败: ' . $e->getMessage()
        ], 500);
    }
})->middleware('jwt.auth');

/**
 * 存储到向量库
 *
 * POST /api/chat/sessions/{session_id}/store-vector
 */
Route::post('/sessions/{session_id}/store-vector', function (Request $request, $session_id) {
    try {
        $session = ChatSession::where('session_id', $session_id)->first();
        if (!$session) {
            return response()->json([
                'code' => 404,
                'msg' => '会话不存在'
            ], 404);
        }

        // 检查质量门槛
        if (!$session->fewshot_eligible) {
            return response()->json([
                'code' => 400,
                'msg' => '会话质量不达标，无法存储到向量库'
            ], 400);
        }

        // 调用DAML-RAG向量存储API
        $vectorResult = callDamlRagVectorStore($session);

        if ($vectorResult['success']) {
            // 更新存储状态
            $session->update([
                'vector_stored' => true,
                'vector_stored_at' => now(),
                'qdrant_point_id' => $vectorResult['vector_id']
            ]);

            return response()->json([
                'code' => 200,
                'msg' => '向量存储成功',
                'data' => [
                    'vector_id' => $vectorResult['vector_id'],
                    'collection_name' => $vectorResult['collection_name']
                ]
            ]);
        } else {
            throw new \Exception('向量存储API调用失败: ' . $vectorResult['error']);
        }

    } catch (\Exception $e) {
        \Log::error('向量存储失败: ' . $e->getMessage(), [
            'session_id' => $session_id
        ]);

        return response()->json([
            'code' => 500,
            'msg' => '向量存储失败: ' . $e->getMessage()
        ], 500);
    }
})->middleware('jwt.auth');

/**
 * 获取个性化报告
 *
 * GET /api/chat/sessions/{session_id}/personalization-report
 */
Route::get('/sessions/{session_id}/personalization-report', function (Request $request, $session_id) {
    try {
        $session = ChatSession::where('session_id', $session_id)->first();
        if (!$session) {
            return response()->json([
                'code' => 404,
                'msg' => '会话不存在'
            ], 404);
        }

        // 验证用户权限
        $userId = auth()->id();
        if ($session->user_id !== $userId) {
            return response()->json([
                'code' => 403,
                'msg' => '无权限访问此会话'
            ], 403);
        }

        // 重新计算个性化指标
        $personalizationMetrics = calculatePersonalizationMetrics($session);

        // 生成个性化报告
        $report = [
            'session_id' => $session_id,
            'utilization_rate' => $personalizationMetrics['utilization_rate'],
            'grade' => $personalizationMetrics['grade'],
            'details' => $personalizationMetrics['details'],
            'recommendations' => generatePersonalizationRecommendations($personalizationMetrics),
            'commercial_insights' => generateCommercialInsights($personalizationMetrics, $session)
        ];

        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => $report
        ]);

    } catch (\Exception $e) {
        \Log::error('获取个性化报告失败: ' . $e->getMessage());

        return response()->json([
            'code' => 500,
            'msg' => '获取报告失败: ' . $e->getMessage()
        ], 500);
    }
})->middleware('jwt.auth');

/**
 * 获取用户会话列表
 *
 * GET /api/chat/sessions/user/{user_id}
 */
Route::get('/sessions/user/{user_id}', function (Request $request, $user_id) {
    try {
        // 验证权限（只能查看自己的会话）
        if (auth()->id() != $user_id) {
            return response()->json([
                'code' => 403,
                'msg' => '无权限访问此用户的会话'
            ], 403);
        }

        $query = ChatSession::where('user_id', $user_id)
            ->orderBy('created_at', 'desc');

        // 应用过滤条件
        if ($request->has('quality_grade')) {
            $query->where('quality_grade', $request->input('quality_grade'));
        }

        if ($request->input('only_fewshot_eligible', false)) {
            $query->where('fewshot_eligible', true);
        }

        // 分页
        $limit = $request->input('limit', 20);
        $page = $request->input('page', 1);
        $sessions = $query->paginate($limit, ['*'], 'page', $page);

        // 计算质量统计
        $qualityStats = calculateUserQualityStats($user_id);

        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => [
                'sessions' => $sessions->items(),
                'total' => $sessions->total(),
                'page' => $page,
                'quality_stats' => $qualityStats
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('获取用户会话失败: ' . $e->getMessage());

        return response()->json([
            'code' => 500,
            'msg' => '获取失败: ' . $e->getMessage()
        ], 500);
    }
})->middleware('jwt.auth');

/**
 * 获取学习数据统计
 *
 * GET /api/chat/sessions/learning-stats
 */
Route::get('/sessions/learning-stats', function (Request $request) {
    try {
        // 只允许管理员访问
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'code' => 403,
                'msg' => '权限不足'
            ], 403);
        }

        // 获取总体统计
        $totalSessions = ChatSession::count();
        $vectorStored = ChatSession::where('vector_stored', true)->count();
        $fewshotEligible = ChatSession::where('fewshot_eligible', true)->count();

        // 质量分布
        $qualityDistribution = ChatSession::selectRaw('quality_grade, COUNT(*) as count')
            ->whereNotNull('quality_grade')
            ->groupBy('quality_grade')
            ->pluck('count', 'quality_grade')
            ->toArray();

        // 平均得分
        $avgPersonalizationScore = ChatSession::whereNotNull('personalization_score')
            ->avg('personalization_score');

        // 学习就绪状态
        $targetSamples = 1000;
        $currentSamples = $fewshotEligible;
        $readinessPercentage = ($currentSamples / $targetSamples) * 100;

        $learningStats = [
            'total_sessions' => $totalSessions,
            'vector_stored' => $vectorStored,
            'fewshot_eligible' => $fewshotEligible,
            'quality_distribution' => $qualityDistribution,
            'avg_personalization_score' => round($avgPersonalizationScore, 2),
            'learning_readiness' => [
                'current_sft_ready' => $currentSamples >= $targetSamples,
                'target_samples' => $targetSamples,
                'current_samples' => $currentSamples,
                'readiness_percentage' => round($readinessPercentage, 1)
            ],
            'data_quality_rate' => $totalSessions > 0 ? round($fewshotEligible / $totalSessions, 3) : 0
        ];

        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => $learningStats
        ]);

    } catch (\Exception $e) {
        \Log::error('获取学习统计失败: ' . $e->getMessage());

        return response()->json([
            'code' => 500,
            'msg' => '获取失败: ' . $e->getMessage()
        ], 500);
    }
})->middleware('jwt.auth');

// ============ 辅助函数 ============

/**
 * 计算三轨评分
 */
function calculateTripleTrackRating($userExperience, $personalization, $expert = null): array
{
    // 计算用户体验得分
    $uxWeights = [
        'practicality' => 0.30,
        'understandability' => 0.20,
        'completeness' => 0.20,
        'overall_satisfaction' => 0.20,
        'friendliness' => 0.10
    ];

    $uxScore = 0;
    foreach ($uxWeights as $key => $weight) {
        $uxScore += ($userExperience[$key] ?? 3) * $weight;
    }

    // 计算个性化得分
    $persWeights = [
        'profile_match' => 0.40,
        'goal_alignment' => 0.30,
        'uniqueness' => 0.20,
        'adaptability' => 0.10
    ];

    $persScore = 0;
    foreach ($persWeights as $key => $weight) {
        $persScore += ($personalization[$key] ?? 3) * $weight;
    }

    // 计算专家得分（如果有）
    $expertScore = null;
    $isSafe = true;

    if ($expert) {
        $expertWeights = [
            'safety_assessment' => 0.25,
            'professional_accuracy' => 0.20,
            'scientific_validity' => 0.20,
            'personalization_quality' => 0.20,
            'completeness' => 0.10,
            'feasibility' => 0.05
        ];

        foreach ($expertWeights as $key => $weight) {
            $expertScore += ($expert[$key] ?? 3) * $weight;
        }

        $expertScore = round($expertScore, 2);
        $isSafe = ($expert['safety_assessment'] ?? 3) >= 3;
    }

    // 计算综合质量得分
    if ($expertScore !== null) {
        $overallQuality = round(($uxScore + $persScore + $expertScore) / 3, 2);
    } else {
        $overallQuality = round(($uxScore + $persScore) / 2, 2);
    }

    // 确定质量等级
    $qualityGrade = determineQualityGrade($overallQuality);

    // 判断Few-Shot资格
    $fewshotEligible = (
        $uxScore >= 4.0 &&
        $persScore >= 4.0 &&
        ($expertScore === null || $expertScore >= 4.0) &&
        $isSafe
    );

    return [
        'user_ux_score' => round($uxScore, 2),
        'personalization_score' => round($persScore, 2),
        'expert_qa_score' => $expertScore,
        'overall_quality' => $overallQuality,
        'quality_grade' => $qualityGrade,
        'fewshot_eligible' => $fewshotEligible,
        'is_safe' => $isSafe
    ];
}

/**
 * 确定质量等级
 */
function determineQualityGrade(float $score): string
{
    if ($score >= 4.7) return 'S';
    if ($score >= 4.3) return 'A';
    if ($score >= 3.8) return 'B';
    if ($score >= 3.0) return 'C';
    return 'D';
}

/**
 * 提取反馈标签
 */
function extractFeedbackTags(string $feedback = null): array
{
    if (empty($feedback)) {
        return [];
    }

    $tags = [];
    $feedbackLower = strtolower($feedback);

    // 情感标签
    if (strpos($feedbackLower, '满意') !== false || strpos($feedbackLower, '好') !== false) {
        $tags[] = 'positive';
    }
    if (strpos($feedbackLower, '不满意') !== false || strpos($feedbackLower, '差') !== false) {
        $tags[] = 'negative';
    }

    // 内容标签
    if (strpos($feedbackLower, '个性化') !== false) {
        $tags[] = 'personalization';
    }
    if (strpos($feedbackLower, '专业') !== false || strpos($feedbackLower, '科学') !== false) {
        $tags[] = 'professional';
    }
    if (strpos($feedbackLower, '安全') !== false || strpos($feedbackLower, '损伤') !== false) {
        $tags[] = 'safety';
    }

    return array_unique($tags);
}

/**
 * 调用DAML-RAG向量存储API
 */
function callDamlRagVectorStore($session): array
{
    try {
        $client = new \GuzzleHttp\Client([
            'timeout' => 30,
            'connect_timeout' => 10
        ]);

        $response = $client->post('http://localhost:8001/api/vector/store/' . $session->session_id, [
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ]);

        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        return [
            'success' => $data['code'] === 200,
            'vector_id' => $data['data']['vector_id'] ?? null,
            'collection_name' => $data['data']['collection_name'] ?? null,
            'error' => $data['msg'] ?? null
        ];

    } catch (\Exception $e) {
        \Log::error('DAML-RAG向量存储API调用失败: ' . $e->getMessage());

        return [
            'success' => false,
            'vector_id' => null,
            'collection_name' => null,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * 生成质量报告
 */
function generateQualityReport($session, $ratingResult): array
{
    return [
        'overall_quality' => $ratingResult['overall_quality'],
        'personalization_level' => $ratingResult['quality_grade'],
        'fewshot_eligible' => $ratingResult['fewshot_eligible'],
        'vector_store_ready' => $ratingResult['fewshot_eligible'],
        'scores_breakdown' => [
            'user_experience' => $ratingResult['user_ux_score'],
            'personalization' => $ratingResult['personalization_score'],
            'expert' => $ratingResult['expert_qa_score']
        ],
        'quality_metrics' => [
            'safety_compliant' => $ratingResult['is_safe'],
            'meets_thresholds' => $ratingResult['fewshot_eligible'],
            'grade_description' => getGradeDescription($ratingResult['quality_grade'])
        ]
    ];
}

/**
 * 计算个性化指标
 */
function calculatePersonalizationMetrics($session): array
{
    // 这里可以集成DAML-RAG的个性化检测算法
    // 暂时返回基于数据库字段的计算结果

    $utilizationRate = $session->profile_utilization_rate ?? 0.5;
    $grade = $session->personalization_grade ?? 'C';

    return [
        'utilization_rate' => $utilizationRate,
        'grade' => $grade,
        'details' => [
            'injury_mentioned' => $session->injury_considered ?? false,
            'equipment_matched' => $session->equipment_matched ?? false,
            'level_appropriate' => $session->level_appropriate ?? false,
            'goal_aligned' => $session->goal_aligned ?? false,
            'recovery_considered' => $session->recovery_considered ?? false
        ]
    ];
}

/**
 * 生成个性化建议
 */
function generatePersonalizationRecommendations($metrics): array
{
    $recommendations = [];

    $details = $metrics['details'];

    if (!$details['injury_mentioned']) {
        $recommendations[] = "建议在计划中更多考虑您的损伤状况，确保训练安全";
    }

    if (!$details['equipment_matched']) {
        $recommendations[] = "部分推荐动作可能需要您没有的器械，可考虑替代动作";
    }

    if (!$details['level_appropriate']) {
        $recommendations[] = "建议调整动作难度，使其更符合您的训练水平";
    }

    if (!$details['goal_aligned']) {
        $recommendations[] = "建议更好地针对您的训练目标选择动作";
    }

    if (!$details['recovery_considered']) {
        $recommendations[] = "建议增加更多关于恢复和休息的指导";
    }

    if ($metrics['utilization_rate'] < 0.6) {
        $recommendations[] = "您的个人档案利用率较低，建议完善档案信息以获得更个性化的建议";
    }

    return $recommendations;
}

/**
 * 生成商业洞察
 */
function generateCommercialInsights($metrics, $session): array
{
    $grade = $metrics['grade'];
    $utilizationRate = $metrics['utilization_rate'];

    $personalizationLevel = getGradeDescription($grade);
    $upgradeSuggestion = null;

    if ($utilizationRate > 0.75 && $grade === 'C') {
        $upgradeSuggestion = "您的档案利用率很高，升级到高级版可获得真正个性化的训练计划";
    } elseif ($utilizationRate > 0.6 && $grade === 'B') {
        $upgradeSuggestion = "升级到精英版，让专家为您定制完全个性化的健身方案";
    }

    return [
        'personalization_level' => $personalizationLevel,
        'upgrade_suggestion' => $upgradeSuggestion,
        'value_metrics' => [
            'profile_utilization' => $utilizationRate,
            'expert_coverage' => $session->expert_qa_score ? 1.0 : 0.0,
            'safety_compliance' => $session->is_safe ? 1.0 : 0.0
        ]
    ];
}

/**
 * 获取等级描述
 */
function getGradeDescription(string $grade): string
{
    $gradeMap = [
        'S' => '真个性化 - 完全定制，档案利用率>90%',
        'A' => '高度个性化 - 档案利用率75-90%',
        'B' => '中度个性化 - 档案利用率60-75%',
        'C' => '基础个性化 - 档案利用率40-60%',
        'D' => '个性化不足 - 档案利用率<40%'
    ];

    return $gradeMap[$grade] ?? $gradeMap['C'];
}

/**
 * 计算用户质量统计
 */
function calculateUserQualityStats($userId): array
{
    $sessions = ChatSession::where('user_id', $userId);

    return [
        'total_sessions' => $sessions->count(),
        'high_quality_rate' => $sessions->where('overall_quality', '>=', 4.0)->count() / max($sessions->count(), 1),
        'avg_personalization' => round($sessions->avg('personalization_score') ?: 0, 2),
        'vector_stored_count' => $sessions->where('vector_stored', true)->count()
    ];
}