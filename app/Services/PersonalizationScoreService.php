<?php

namespace App\Services;

use App\Models\ChatSession;
use Illuminate\Support\Facades\Log;

/**
 * PersonalizationScoreService - 个性化感知评分计算服务
 * 
 * 自动计算个性化感知评分的4个维度：
 * 1. 档案利用率 (profile_utilization_rate): 0-100%
 * 2. 目标对齐度 (goal_alignment): 0-100%
 * 3. 独特性 (uniqueness): 0-100%
 * 4. 动态调整 (dynamic_adjustment): 0-100%
 * 
 * 计算依据：
 * - 会话元数据 (metadata)
 * - 使用的工具列表 (tools_used)
 * - 用户档案信息
 * - 对话历史
 * 
 * @version 1.0.0
 * @date 2025-12-31
 */
class PersonalizationScoreService
{
    /**
     * 档案相关工具列表
     */
    private const PROFILE_TOOLS = [
        'get_user_profile',
        'user_profile_integrator',
        'find_similar_training_cases',
    ];

    /**
     * 目标相关工具列表
     */
    private const GOAL_TOOLS = [
        'training_goal_recommender',
        'professional_program_designer',
        'periodized_program_designer',
    ];

    /**
     * 个性化相关工具列表
     */
    private const PERSONALIZATION_TOOLS = [
        'intelligent_exercise_selector',
        'contraindications_checker',
        'injury_risk_assessor',
        'safe_exercise_modifier',
        'exercise_alternative_finder',
    ];

    /**
     * 计算所有个性化感知评分
     * 
     * @param ChatSession $session
     * @return array
     */
    public function calculateAllScores(ChatSession $session): array
    {
        $metadata = $session->metadata ?? [];
        $toolsUsed = $session->tools_used ?? [];

        return [
            'profile_utilization_rate' => $this->calculateProfileUtilization($session, $metadata, $toolsUsed),
            'goal_alignment' => $this->calculateGoalAlignment($session, $metadata, $toolsUsed),
            'uniqueness' => $this->calculateUniqueness($session, $metadata, $toolsUsed),
            'dynamic_adjustment' => $this->calculateDynamicAdjustment($session, $metadata),
        ];
    }

    /**
     * 计算档案利用率
     * 
     * 评估系统对用户档案信息的使用程度
     * 
     * 计算因素：
     * - 是否使用了档案相关工具 (+20分/工具)
     * - 是否引用了用户等级 (+15分)
     * - 是否引用了用户目标 (+15分)
     * - 是否引用了用户伤病 (+15分)
     * - 是否引用了用户器械 (+15分)
     * - 是否引用了用户偏好 (+10分)
     * - 是否引用了用户历史 (+10分)
     * 
     * @param ChatSession $session
     * @param array $metadata
     * @param array $toolsUsed
     * @return float 0-100
     */
    public function calculateProfileUtilization(ChatSession $session, array $metadata, array $toolsUsed): float
    {
        $score = 0;

        // 检查是否使用了档案相关工具
        foreach (self::PROFILE_TOOLS as $tool) {
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

        // 检查回复中是否提及个性化内容
        $response = $session->llm_response ?? '';
        $personalizedKeywords = [
            '根据您的', '针对您的', '考虑到您', '您的情况',
            '您的目标', '您的水平', '您的伤病', '您的器械',
        ];

        foreach ($personalizedKeywords as $keyword) {
            if (strpos($response, $keyword) !== false) {
                $score += 5;
                break; // 只加一次
            }
        }

        return min($score, 100);
    }

    /**
     * 计算目标对齐度
     * 
     * 评估系统建议与用户目标的匹配程度
     * 
     * 计算因素：
     * - 是否识别了用户目标 (+20分)
     * - 是否使用了目标相关工具 (+15分/工具)
     * - 是否针对目标给出建议 (+20分)
     * - 回复中是否提及目标关键词 (+10分)
     * 
     * @param ChatSession $session
     * @param array $metadata
     * @param array $toolsUsed
     * @return float 0-100
     */
    public function calculateGoalAlignment(ChatSession $session, array $metadata, array $toolsUsed): float
    {
        $score = 40; // 基础分

        // 检查是否识别了用户目标
        if (isset($metadata['detected_goal']) && !empty($metadata['detected_goal'])) {
            $score += 20;
        }

        // 检查是否使用了目标相关工具
        foreach (self::GOAL_TOOLS as $tool) {
            if (in_array($tool, $toolsUsed)) {
                $score += 15;
            }
        }

        // 检查是否针对目标给出建议
        if (isset($metadata['goal_specific_advice']) && $metadata['goal_specific_advice']) {
            $score += 20;
        }

        // 检查回复中是否提及目标关键词
        $response = $session->llm_response ?? '';
        $goalKeywords = [
            '增肌', '减脂', '力量', '耐力', '塑形', '康复',
            '目标', '计划', '周期', '进度',
        ];

        $goalMentions = 0;
        foreach ($goalKeywords as $keyword) {
            if (strpos($response, $keyword) !== false) {
                $goalMentions++;
            }
        }

        if ($goalMentions >= 3) {
            $score += 10;
        } elseif ($goalMentions >= 1) {
            $score += 5;
        }

        return min($score, 100);
    }

    /**
     * 计算独特性
     * 
     * 评估回复的个性化程度（非通用模板）
     * 
     * 计算因素：
     * - 是否使用了个性化工具 (+10分/工具)
     * - 回复长度（更长通常更个性化）
     * - 是否包含具体数据（组数、次数、重量等）
     * - 是否包含具体动作名称
     * 
     * @param ChatSession $session
     * @param array $metadata
     * @param array $toolsUsed
     * @return float 0-100
     */
    public function calculateUniqueness(ChatSession $session, array $metadata, array $toolsUsed): float
    {
        $score = 40; // 基础分

        // 检查是否使用了个性化工具
        foreach (self::PERSONALIZATION_TOOLS as $tool) {
            if (in_array($tool, $toolsUsed)) {
                $score += 10;
            }
        }

        $response = $session->llm_response ?? '';

        // 检查回复长度
        $responseLength = mb_strlen($response);
        if ($responseLength > 1000) {
            $score += 15;
        } elseif ($responseLength > 500) {
            $score += 10;
        } elseif ($responseLength > 200) {
            $score += 5;
        }

        // 检查是否包含具体数据
        $hasSpecificData = preg_match('/\d+\s*(组|次|kg|磅|分钟|秒)/', $response);
        if ($hasSpecificData) {
            $score += 10;
        }

        // 检查是否包含具体动作名称（中文动作名）
        $exercisePatterns = [
            '深蹲', '硬拉', '卧推', '引体向上', '划船',
            '推举', '弯举', '三头', '腿举', '腿弯举',
        ];

        $exerciseMentions = 0;
        foreach ($exercisePatterns as $pattern) {
            if (strpos($response, $pattern) !== false) {
                $exerciseMentions++;
            }
        }

        if ($exerciseMentions >= 3) {
            $score += 10;
        } elseif ($exerciseMentions >= 1) {
            $score += 5;
        }

        return min($score, 100);
    }

    /**
     * 计算动态调整
     * 
     * 评估系统是否根据上下文和反馈进行调整
     * 
     * 计算因素：
     * - 是否是多轮对话 (+20分)
     * - 是否有上下文引用 (+15分)
     * - 是否根据反馈调整 (+15分)
     * - 是否引用了之前的对话内容 (+10分)
     * 
     * @param ChatSession $session
     * @param array $metadata
     * @return float 0-100
     */
    public function calculateDynamicAdjustment(ChatSession $session, array $metadata): float
    {
        $score = 40; // 基础分

        // 检查是否是多轮对话
        $conversationCount = ChatSession::where('session_id', $session->session_id)->count();
        if ($conversationCount > 3) {
            $score += 20;
        } elseif ($conversationCount > 1) {
            $score += 15;
        }

        // 检查是否有上下文引用
        if (isset($metadata['context_used']) && $metadata['context_used']) {
            $score += 15;
        }

        // 检查是否根据反馈调整
        if (isset($metadata['feedback_incorporated']) && $metadata['feedback_incorporated']) {
            $score += 15;
        }

        // 检查回复中是否引用了之前的对话
        $response = $session->llm_response ?? '';
        $contextKeywords = [
            '之前', '刚才', '上次', '您提到', '您说的',
            '根据我们的对话', '继续', '补充',
        ];

        foreach ($contextKeywords as $keyword) {
            if (strpos($response, $keyword) !== false) {
                $score += 10;
                break;
            }
        }

        return min($score, 100);
    }

    /**
     * 根据档案利用率计算个性化等级
     * 
     * S级: 90-100% - 真个性化
     * A级: 75-89%  - 高度个性化
     * B级: 60-74%  - 中度个性化
     * C级: 40-59%  - 基础个性化
     * D级: 0-39%   - 个性化不足
     * 
     * @param float $utilizationRate
     * @return string
     */
    public function calculateGrade(float $utilizationRate): string
    {
        if ($utilizationRate >= 90) return 'S';
        if ($utilizationRate >= 75) return 'A';
        if ($utilizationRate >= 60) return 'B';
        if ($utilizationRate >= 40) return 'C';
        return 'D';
    }

    /**
     * 获取等级描述
     * 
     * @param string $grade
     * @return string
     */
    public function getGradeDescription(string $grade): string
    {
        $descriptions = [
            'S' => '真个性化 - 完全定制，档案利用率>90%',
            'A' => '高度个性化 - 档案利用率75-90%',
            'B' => '中度个性化 - 档案利用率60-75%',
            'C' => '基础个性化 - 档案利用率40-60%',
            'D' => '个性化不足 - 档案利用率<40%',
        ];

        return $descriptions[$grade] ?? $descriptions['C'];
    }

    /**
     * 更新会话的个性化评分
     * 
     * @param ChatSession $session
     * @return array 更新后的评分
     */
    public function updateSessionScores(ChatSession $session): array
    {
        $scores = $this->calculateAllScores($session);

        $session->profile_utilization_rate = $scores['profile_utilization_rate'];
        $session->goal_alignment = $scores['goal_alignment'];
        $session->uniqueness = $scores['uniqueness'];
        $session->dynamic_adjustment = $scores['dynamic_adjustment'];
        $session->personalization_grade = $this->calculateGrade($scores['profile_utilization_rate']);

        $session->save();

        Log::info('个性化评分已更新', [
            'session_id' => $session->session_id,
            'scores' => $scores,
            'grade' => $session->personalization_grade,
        ]);

        return $scores;
    }

    /**
     * 批量更新所有未评分会话的个性化评分
     * 
     * @param int $limit 每次处理的数量
     * @return int 处理的会话数量
     */
    public function batchUpdateScores(int $limit = 100): int
    {
        $sessions = ChatSession::whereNull('profile_utilization_rate')
            ->limit($limit)
            ->get();

        $count = 0;
        foreach ($sessions as $session) {
            try {
                $this->updateSessionScores($session);
                $count++;
            } catch (\Exception $e) {
                Log::error('批量更新个性化评分失败', [
                    'session_id' => $session->session_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
