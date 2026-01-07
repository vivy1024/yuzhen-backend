<?php

namespace App\Services;

use App\Models\ChatSession;
use App\Models\ExpertReview;
use Illuminate\Support\Facades\Log;

/**
 * FewShotEligibilityService - Few-Shot准入规则服务
 * 
 * 实现Few-Shot池准入规则：
 * 1. 三轨高分（≥4.0）检查
 * 2. 安全性一票否决（<3）
 * 3. 冷启动期保护（前3条对话）
 * 
 * @version 1.0.0
 * @date 2025-12-31
 */
class FewShotEligibilityService
{
    /**
     * 三轨评分门槛
     */
    private const THRESHOLD_SCORE = 4.0;

    /**
     * 安全性一票否决门槛
     */
    private const SAFETY_VETO_THRESHOLD = 3;

    /**
     * 冷启动期对话数量
     */
    private const COLD_START_COUNT = 3;

    /**
     * 冷启动期降低的门槛
     */
    private const COLD_START_THRESHOLD = 3.5;

    /**
     * 检查会话是否符合Few-Shot准入条件
     * 
     * @param ChatSession $session
     * @return array 包含结果和详细原因
     */
    public function checkEligibility(ChatSession $session): array
    {
        $result = [
            'eligible' => false,
            'reason' => '',
            'details' => [
                'user_experience_avg' => null,
                'personalization_avg' => null,
                'expert_avg' => null,
                'safety_score' => null,
                'is_cold_start' => false,
                'cold_start_session_count' => 0,
            ],
        ];

        // 检查是否在冷启动期
        $coldStartInfo = $this->checkColdStartPeriod($session);
        $result['details']['is_cold_start'] = $coldStartInfo['is_cold_start'];
        $result['details']['cold_start_session_count'] = $coldStartInfo['session_count'];

        if ($coldStartInfo['is_cold_start']) {
            return $this->checkColdStartEligibility($session, $result);
        }

        // 正常情况：检查三轨评分
        return $this->checkThreeTrackEligibility($session, $result);
    }

    /**
     * 检查是否在冷启动期
     * 
     * @param ChatSession $session
     * @return array
     */
    private function checkColdStartPeriod(ChatSession $session): array
    {
        if (!$session->user_id) {
            return [
                'is_cold_start' => false,
                'session_count' => 0,
            ];
        }

        $sessionCount = ChatSession::where('user_id', $session->user_id)->count();

        return [
            'is_cold_start' => $sessionCount <= self::COLD_START_COUNT,
            'session_count' => $sessionCount,
        ];
    }

    /**
     * 冷启动期准入检查
     * 
     * 冷启动期（前3条对话）降低门槛：
     * - 只要用户体验评分平均值 >= 3.5 即可
     * 
     * @param ChatSession $session
     * @param array $result
     * @return array
     */
    private function checkColdStartEligibility(ChatSession $session, array $result): array
    {
        $uxAvg = $this->calculateUserExperienceAverage($session);
        $result['details']['user_experience_avg'] = $uxAvg;

        if ($uxAvg === null) {
            $result['reason'] = '用户体验评分未完成';
            return $result;
        }

        if ($uxAvg >= self::COLD_START_THRESHOLD) {
            $result['eligible'] = true;
            $result['reason'] = sprintf(
                '冷启动期通过（用户体验评分 %.2f >= %.1f）',
                $uxAvg,
                self::COLD_START_THRESHOLD
            );
        } else {
            $result['reason'] = sprintf(
                '冷启动期未通过（用户体验评分 %.2f < %.1f）',
                $uxAvg,
                self::COLD_START_THRESHOLD
            );
        }

        return $result;
    }

    /**
     * 三轨评分准入检查
     * 
     * 准入条件：
     * 1. 用户体验评分平均值 >= 4.0
     * 2. 个性化感知评分平均值 >= 4.0（转换为5分制）
     * 3. 专家专业评分平均值 >= 4.0（如果有）
     * 4. 安全性评分 >= 3（否则一票否决）
     * 
     * @param ChatSession $session
     * @param array $result
     * @return array
     */
    private function checkThreeTrackEligibility(ChatSession $session, array $result): array
    {
        // 1. 检查用户体验评分
        $uxAvg = $this->calculateUserExperienceAverage($session);
        $result['details']['user_experience_avg'] = $uxAvg;

        if ($uxAvg === null) {
            $result['reason'] = '用户体验评分未完成';
            return $result;
        }

        if ($uxAvg < self::THRESHOLD_SCORE) {
            $result['reason'] = sprintf(
                '用户体验评分不达标（%.2f < %.1f）',
                $uxAvg,
                self::THRESHOLD_SCORE
            );
            return $result;
        }

        // 2. 检查个性化感知评分
        $personalizationAvg = $this->calculatePersonalizationAverage($session);
        $result['details']['personalization_avg'] = $personalizationAvg;

        if ($personalizationAvg === null) {
            $result['reason'] = '个性化感知评分未完成';
            return $result;
        }

        if ($personalizationAvg < self::THRESHOLD_SCORE) {
            $result['reason'] = sprintf(
                '个性化感知评分不达标（%.2f < %.1f）',
                $personalizationAvg,
                self::THRESHOLD_SCORE
            );
            return $result;
        }

        // 3. 检查专家评分（如果有）
        $expertReview = $session->expertReviews()->first();
        if ($expertReview) {
            // 3.1 安全性一票否决
            $result['details']['safety_score'] = $expertReview->safety;
            
            if ($expertReview->safety < self::SAFETY_VETO_THRESHOLD) {
                $result['reason'] = sprintf(
                    '安全性一票否决（安全性评分 %d < %d）',
                    $expertReview->safety,
                    self::SAFETY_VETO_THRESHOLD
                );
                return $result;
            }

            // 3.2 专家评分平均值检查
            $expertAvg = $expertReview->getAverageScore();
            $result['details']['expert_avg'] = $expertAvg;

            if ($expertAvg < self::THRESHOLD_SCORE) {
                $result['reason'] = sprintf(
                    '专家评分不达标（%.2f < %.1f）',
                    $expertAvg,
                    self::THRESHOLD_SCORE
                );
                return $result;
            }
        }

        // 所有检查通过
        $result['eligible'] = true;
        $result['reason'] = '三轨评分全部达标';

        return $result;
    }

    /**
     * 计算用户体验评分平均值
     * 
     * @param ChatSession $session
     * @return float|null
     */
    public function calculateUserExperienceAverage(ChatSession $session): ?float
    {
        $scores = array_filter([
            $session->ux_clarity,
            $session->ux_practicality,
            $session->ux_detail,
            $session->ux_friendliness,
            $session->ux_satisfaction,
        ], fn($v) => $v !== null);

        if (empty($scores)) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * 计算个性化感知评分平均值（转换为5分制）
     * 
     * 原始分数为0-100%，转换为5分制：100% = 5分
     * 
     * @param ChatSession $session
     * @return float|null
     */
    public function calculatePersonalizationAverage(ChatSession $session): ?float
    {
        $scores = array_filter([
            $session->profile_utilization_rate,
            $session->goal_alignment,
            $session->uniqueness,
            $session->dynamic_adjustment,
        ], fn($v) => $v !== null);

        if (empty($scores)) {
            return null;
        }

        // 转换为5分制：100% -> 5分
        $avgPercentage = array_sum($scores) / count($scores);
        return round($avgPercentage / 20, 2);
    }

    /**
     * 批量检查并更新会话的Few-Shot资格
     * 
     * @param int $limit 每次处理的数量
     * @return array 处理结果统计
     */
    public function batchUpdateEligibility(int $limit = 100): array
    {
        $sessions = ChatSession::whereNotNull('ux_clarity')
            ->whereNull('fewshot_eligible')
            ->orWhere(function ($query) {
                $query->whereNotNull('ux_clarity')
                      ->where('updated_at', '>', now()->subHours(24));
            })
            ->limit($limit)
            ->get();

        $stats = [
            'total' => $sessions->count(),
            'eligible' => 0,
            'not_eligible' => 0,
            'errors' => 0,
        ];

        foreach ($sessions as $session) {
            try {
                $result = $this->checkEligibility($session);
                $session->fewshot_eligible = $result['eligible'];
                $session->save();

                if ($result['eligible']) {
                    $stats['eligible']++;
                } else {
                    $stats['not_eligible']++;
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('批量更新Few-Shot资格失败', [
                    'session_id' => $session->session_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * 获取Few-Shot池统计信息
     * 
     * @return array
     */
    public function getPoolStats(): array
    {
        $totalEligible = ChatSession::where('fewshot_eligible', true)->count();
        $totalSessions = ChatSession::count();
        $ratedSessions = ChatSession::whereNotNull('ux_clarity')->count();

        // 按等级统计
        $gradeStats = ChatSession::where('fewshot_eligible', true)
            ->selectRaw('personalization_grade, COUNT(*) as count')
            ->whereNotNull('personalization_grade')
            ->groupBy('personalization_grade')
            ->pluck('count', 'personalization_grade')
            ->toArray();

        // 安全性否决统计
        $safetyVetoCount = ExpertReview::where('safety', '<', self::SAFETY_VETO_THRESHOLD)->count();

        return [
            'total_sessions' => $totalSessions,
            'rated_sessions' => $ratedSessions,
            'eligible_sessions' => $totalEligible,
            'eligibility_rate' => $ratedSessions > 0 
                ? round($totalEligible / $ratedSessions * 100, 2) 
                : 0,
            'grade_distribution' => $gradeStats,
            'safety_veto_count' => $safetyVetoCount,
            'thresholds' => [
                'score_threshold' => self::THRESHOLD_SCORE,
                'safety_veto_threshold' => self::SAFETY_VETO_THRESHOLD,
                'cold_start_count' => self::COLD_START_COUNT,
                'cold_start_threshold' => self::COLD_START_THRESHOLD,
            ],
        ];
    }
}
