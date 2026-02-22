<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * User Profile Model v2.0
 * 
 * 用户档案模型 - 完整JSON结构存储
 * 完全对齐前端 TypeScript 类型定义
 * 
 * @property int $user_id
 * @property array $basic_info 基础信息
 * @property array $fitness_goals 健身目标
 * @property array $training_preferences 训练偏好
 * @property array|null $strength_data 力量数据
 * @property array|null $strength_progress 力量进步曲线
 * @property array|null $training_feedback 训练反馈记录
 * @property array $health_status 健康状况
 * @property array $nutrition_profile 营养档案
 * @property array|null $ffmi_assessment FFMI评估
 * @property int $version 版本号
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class UserProfile extends Model
{
    use HasFactory;

    protected $table = 'user_profiles';

    /**
     * 可批量赋值字段
     */
    protected $fillable = [
        'user_id',
        'basic_info',
        'fitness_goals',
        'training_preferences',
        'preferred_rest_pattern',
        'health_status',
        'nutrition_profile',
        'strength_data',
        'strength_progress',
        'training_feedback',
        'ffmi_assessment',
        'streak_days',
        'total_training_days',
        'last_training_date',
        'version',
        'last_sync_at',
        'sync_status',
        'is_mcp_temp',
        'mcp_session_id',
        'sync_source',
    ];

    /**
     * 字段类型转换
     */
    protected $casts = [
        'basic_info' => 'array',
        'fitness_goals' => 'array',
        'training_preferences' => 'array',
        'health_status' => 'array',
        'nutrition_profile' => 'array',
        'strength_data' => 'array',
        'strength_progress' => 'array',
        'training_feedback' => 'array',
        'ffmi_assessment' => 'array',
        'version' => 'integer',
        'is_mcp_temp' => 'boolean',
        'last_sync_at' => 'datetime',
        'streak_days' => 'integer',
        'total_training_days' => 'integer',
        'last_training_date' => 'date',
    ];

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 更新训练连续天数（训练记录提交时调用）
     */
    public function updateTrainingStreak(string $trainingDate): void
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $lastDate = $this->last_training_date?->toDateString();

        // 同一天重复训练不重复计数
        if ($lastDate === $trainingDate) {
            return;
        }

        // 计算连续天数
        if ($lastDate === $yesterday || $lastDate === $today) {
            // 昨天或今天训练过 → 连续+1
            $this->streak_days += 1;
        } elseif ($lastDate === null) {
            // 首次训练
            $this->streak_days = 1;
        } else {
            // 中断了 → 重新开始
            $this->streak_days = 1;
        }

        $this->total_training_days += 1;
        $this->last_training_date = $trainingDate;
        $this->save();
    }

    /**
     * 获取成就徽章列表
     */
    public function getAchievements(): array
    {
        $badges = [];
        $milestones = [
            ['days' => 7, 'name' => '初露锋芒', 'icon' => '🔥', 'description' => '连续训练7天'],
            ['days' => 30, 'name' => '坚持不懈', 'icon' => '💪', 'description' => '连续训练30天'],
            ['days' => 100, 'name' => '百日铁人', 'icon' => '🏆', 'description' => '连续训练100天'],
            ['days' => 365, 'name' => '年度传奇', 'icon' => '👑', 'description' => '连续训练365天'],
        ];

        foreach ($milestones as $milestone) {
            $badges[] = [
                'name' => $milestone['name'],
                'icon' => $milestone['icon'],
                'description' => $milestone['description'],
                'requiredDays' => $milestone['days'],
                'unlocked' => $this->streak_days >= $milestone['days'],
            ];
        }

        // 累计训练天数成就
        $totalMilestones = [
            ['days' => 10, 'name' => '起步者', 'icon' => '🌱'],
            ['days' => 50, 'name' => '训练达人', 'icon' => '⭐'],
            ['days' => 200, 'name' => '健身老手', 'icon' => '💎'],
            ['days' => 500, 'name' => '铁血战士', 'icon' => '🎖️'],
        ];

        foreach ($totalMilestones as $milestone) {
            $badges[] = [
                'name' => $milestone['name'],
                'icon' => $milestone['icon'],
                'description' => "累计训练{$milestone['days']}天",
                'requiredDays' => $milestone['days'],
                'unlocked' => $this->total_training_days >= $milestone['days'],
                'type' => 'total',
            ];
        }

        return $badges;
    }

    /**
     * 获取昵称（从basic_info中）
     */
    public function getNicknameAttribute(): ?string
    {
        return $this->basic_info['nickname'] ?? null;
    }

    /**
     * 获取年龄（从basic_info中）
     */
    public function getAgeAttribute(): ?int
    {
        return $this->basic_info['age'] ?? null;
    }

    /**
     * 获取身高（从basic_info中）
     */
    public function getHeightAttribute(): ?float
    {
        return $this->basic_info['height'] ?? null;
    }

    /**
     * 获取体重（从basic_info中）
     */
    public function getWeightAttribute(): ?float
    {
        return $this->basic_info['weight'] ?? null;
    }

    /**
     * 计算BMI（从basic_info读取数据）
     */
    public function getBmiAttribute(): ?float
    {
        $height = $this->basic_info['height'] ?? null;
        $weight = $this->basic_info['weight'] ?? null;
        
        if (!$height || !$weight) {
            return null;
        }
        
        $heightInMeters = $height / 100;
        return round($weight / ($heightInMeters * $heightInMeters), 2);
    }

    /**
     * 计算FFMI（如果已有评估数据则直接返回）
     */
    public function getFfmiAttribute(): ?float
    {
        return $this->ffmi_assessment['ffmi'] ?? null;
    }

    /**
     * 获取首选休息模式
     */
    public function getPreferredRestPatternAttribute(): ?string
    {
        return $this->attributes['preferred_rest_pattern'] ?? null;
    }

    /**
     * 根据训练水平获取推荐的休息模式
     */
    public function getRecommendedRestPattern(): ?string
    {
        $fitnessLevel = $this->basic_info['fitness_level'] ?? null;
        
        if (!$fitnessLevel) {
            return null;
        }

        // 根据训练水平推荐休息模式（统一4级标准）
        $recommendations = [
            'novice' => '练一休一',         // 零基础：需要更多恢复时间
            'beginner' => '练二休一',       // 初级：适度增加训练频率
            'intermediate' => '练三休一',   // 中级：标准训练频率
            'advanced' => '练四休一',       // 高级：高频率训练
        ];

        return $recommendations[$fitnessLevel] ?? '练三休一';
    }

    /**
     * 检查档案是否完整
     */
    public function isComplete(): bool
    {
        return !empty($this->basic_info) 
            && !empty($this->fitness_goals) 
            && !empty($this->training_preferences)
            && !empty($this->health_status)
            && !empty($this->nutrition_profile);
    }

    /**
     * 递增版本号
     */
    public function incrementVersion(): void
    {
        $this->increment('version');
    }

    /**
     * 记录训练数据（力量进步）
     * 
     * @param string $exerciseName 动作名称（如：squat, bench_press, deadlift）
     * @param float $weight 重量（kg）
     * @param int $reps 次数
     * @param string|null $date 日期（ISO 8601格式，默认今天）
     * @return array 更新后的力量进步数据
     */
    public function recordStrengthProgress(
        string $exerciseName, 
        float $weight, 
        int $reps, 
        ?string $date = null
    ): array {
        $date = $date ?? now()->toISOString();
        
        // 估算1RM（使用Epley公式）
        $estimated1RM = $this->estimate1RM($weight, $reps);
        
        // 获取当前力量进步数据
        $strengthProgress = $this->strength_progress ?? [];
        
        // 初始化动作数据（如果不存在）
        if (!isset($strengthProgress[$exerciseName])) {
            $strengthProgress[$exerciseName] = [
                'history' => [],
                'current_1rm' => null,
                'strength_level' => null,
                'last_updated' => null,
            ];
        }
        
        // 添加新记录到历史
        $strengthProgress[$exerciseName]['history'][] = [
            'weight' => $weight,
            'reps' => $reps,
            'date' => $date,
            'estimated_1rm' => $estimated1RM,
        ];
        
        // 更新当前1RM（取历史最大值）
        $strengthProgress[$exerciseName]['current_1rm'] = $estimated1RM;
        
        // 评估力量水平
        $strengthProgress[$exerciseName]['strength_level'] = $this->assessStrengthLevel(
            $exerciseName, 
            $estimated1RM
        );
        
        // 更新最后更新时间
        $strengthProgress[$exerciseName]['last_updated'] = $date;
        
        // 保存到数据库
        $this->strength_progress = $strengthProgress;
        $this->save();
        
        return $strengthProgress[$exerciseName];
    }

    /**
     * 估算1RM（使用Epley公式）
     * 
     * 公式：1RM = weight × (1 + reps / 30)
     * 
     * @param float $weight 重量（kg）
     * @param int $reps 次数
     * @return float 估算的1RM
     */
    private function estimate1RM(float $weight, int $reps): float
    {
        if ($reps === 1) {
            return $weight;
        }
        
        // Epley公式
        $estimated1RM = $weight * (1 + $reps / 30);
        
        return round($estimated1RM, 1);
    }

    /**
     * 评估力量水平
     * 
     * 根据1RM和体重评估力量水平（参考StrengthStandard节点）
     * 
     * @param string $exerciseName 动作名称
     * @param float $oneRM 1RM重量（kg）
     * @return string 力量水平（novice/beginner/intermediate/advanced）
     */
    private function assessStrengthLevel(string $exerciseName, float $oneRM): string
    {
        $bodyWeight = $this->basic_info['weight'] ?? null;
        
        if (!$bodyWeight) {
            return 'unknown';
        }
        
        // 计算相对力量（1RM / 体重）
        $relativeStrength = $oneRM / $bodyWeight;
        
        // 力量标准（统一4级标准）
        // 这里使用深蹲的标准作为示例
        $standards = [
            'squat' => [
                'novice' => 0.5,        // 0.5倍体重
                'beginner' => 1.0,      // 1.0倍体重
                'intermediate' => 1.5,  // 1.5倍体重
                'advanced' => 2.0,      // 2.0倍体重
            ],
            'bench_press' => [
                'novice' => 0.3,
                'beginner' => 0.6,
                'intermediate' => 1.0,
                'advanced' => 1.5,
            ],
            'deadlift' => [
                'novice' => 0.75,
                'beginner' => 1.25,
                'intermediate' => 1.75,
                'advanced' => 2.5,
            ],
        ];
        
        // 获取该动作的标准（如果不存在，使用深蹲标准）
        $exerciseStandards = $standards[$exerciseName] ?? $standards['squat'];
        
        // 评估力量水平
        if ($relativeStrength < $exerciseStandards['novice']) {
            return 'untrained';
        } elseif ($relativeStrength < $exerciseStandards['beginner']) {
            return 'novice';
        } elseif ($relativeStrength < $exerciseStandards['intermediate']) {
            return 'beginner';
        } elseif ($relativeStrength < $exerciseStandards['advanced']) {
            return 'intermediate';
        } else {
            return 'advanced';
        }
    }

    /**
     * 获取动作的力量进步曲线
     * 
     * @param string $exerciseName 动作名称
     * @return array|null 力量进步数据
     */
    public function getStrengthProgress(string $exerciseName): ?array
    {
        $strengthProgress = $this->strength_progress ?? [];
        
        return $strengthProgress[$exerciseName] ?? null;
    }

    /**
     * 获取所有动作的当前1RM
     * 
     * @return array 动作名称 => 1RM的映射
     */
    public function getAllCurrent1RMs(): array
    {
        $strengthProgress = $this->strength_progress ?? [];
        $current1RMs = [];
        
        foreach ($strengthProgress as $exerciseName => $data) {
            if (isset($data['current_1rm'])) {
                $current1RMs[$exerciseName] = $data['current_1rm'];
            }
        }
        
        return $current1RMs;
    }

    /**
     * 获取整体力量水平（基于主要动作的平均水平）
     * 
     * @return string 整体力量水平
     */
    public function getOverallStrengthLevel(): string
    {
        $strengthProgress = $this->strength_progress ?? [];
        
        // 主要动作
        $mainExercises = ['squat', 'bench_press', 'deadlift'];
        $levels = [];
        
        foreach ($mainExercises as $exercise) {
            if (isset($strengthProgress[$exercise]['strength_level'])) {
                $levels[] = $strengthProgress[$exercise]['strength_level'];
            }
        }
        
        if (empty($levels)) {
            return 'unknown';
        }
        
        // 力量水平映射到数值（统一4级标准）
        $levelMap = [
            'untrained' => 0,
            'novice' => 1,
            'beginner' => 2,
            'intermediate' => 3,
            'advanced' => 4,
        ];
        
        // 计算平均水平
        $numericLevels = array_map(fn($level) => $levelMap[$level] ?? 0, $levels);
        $averageLevel = array_sum($numericLevels) / count($numericLevels);
        
        // 映射回力量水平
        $reverseLevelMap = array_flip($levelMap);
        $roundedLevel = round($averageLevel);
        
        return $reverseLevelMap[$roundedLevel] ?? 'unknown';
    }

    /**
     * 记录训练反馈
     * 
     * @param string $sessionId 训练会话ID
     * @param int $fatigueLevel 疲劳程度（1-10分）
     * @param string $subjectiveFeeling 主观感受
     * @param array $trainingRecords 训练记录（动作、组数、次数、重量等）
     * @param string|null $date 日期（ISO 8601格式，默认今天）
     * @return array 更新后的训练反馈数据
     */
    public function recordTrainingFeedback(
        string $sessionId,
        int $fatigueLevel,
        string $subjectiveFeeling,
        array $trainingRecords,
        ?string $date = null
    ): array {
        // 验证疲劳程度范围
        if ($fatigueLevel < 1 || $fatigueLevel > 10) {
            throw new \InvalidArgumentException('疲劳程度必须在1-10之间');
        }
        
        $date = $date ?? now()->toISOString();
        
        // 获取当前训练反馈数据
        $trainingFeedback = $this->training_feedback ?? [];
        
        // 创建新的反馈记录
        $feedbackRecord = [
            'session_id' => $sessionId,
            'date' => $date,
            'fatigue_level' => $fatigueLevel,
            'subjective_feeling' => $subjectiveFeeling,
            'training_records' => $trainingRecords,
            'created_at' => now()->toISOString(),
        ];
        
        // 添加到反馈列表
        $trainingFeedback[] = $feedbackRecord;
        
        // 保存到数据库
        $this->training_feedback = $trainingFeedback;
        $this->save();
        
        return $feedbackRecord;
    }

    /**
     * 获取训练反馈历史
     * 
     * @param int|null $limit 限制返回数量（默认返回所有）
     * @param string|null $startDate 开始日期（ISO 8601格式）
     * @param string|null $endDate 结束日期（ISO 8601格式）
     * @return array 训练反馈历史
     */
    public function getTrainingFeedbackHistory(
        ?int $limit = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $trainingFeedback = $this->training_feedback ?? [];
        
        // 按日期过滤
        if ($startDate || $endDate) {
            $trainingFeedback = array_filter($trainingFeedback, function ($feedback) use ($startDate, $endDate) {
                $feedbackDate = $feedback['date'] ?? null;
                
                if (!$feedbackDate) {
                    return false;
                }
                
                if ($startDate && $feedbackDate < $startDate) {
                    return false;
                }
                
                if ($endDate && $feedbackDate > $endDate) {
                    return false;
                }
                
                return true;
            });
        }
        
        // 按日期倒序排序（最新的在前）
        usort($trainingFeedback, function ($a, $b) {
            return strcmp($b['date'] ?? '', $a['date'] ?? '');
        });
        
        // 限制返回数量
        if ($limit !== null && $limit > 0) {
            $trainingFeedback = array_slice($trainingFeedback, 0, $limit);
        }
        
        return $trainingFeedback;
    }

    /**
     * 获取平均疲劳程度
     * 
     * @param int|null $days 统计最近N天的数据（默认30天）
     * @return float|null 平均疲劳程度
     */
    public function getAverageFatigueLevel(?int $days = 30): ?float
    {
        $startDate = now()->subDays($days)->toISOString();
        $feedbackHistory = $this->getTrainingFeedbackHistory(null, $startDate);
        
        if (empty($feedbackHistory)) {
            return null;
        }
        
        $fatigueLevels = array_map(fn($feedback) => $feedback['fatigue_level'] ?? 0, $feedbackHistory);
        $averageFatigue = array_sum($fatigueLevels) / count($fatigueLevels);
        
        return round($averageFatigue, 1);
    }

    /**
     * 获取最近的训练反馈
     * 
     * @return array|null 最近的训练反馈
     */
    public function getLatestTrainingFeedback(): ?array
    {
        $feedbackHistory = $this->getTrainingFeedbackHistory(1);
        
        return $feedbackHistory[0] ?? null;
    }

    /**
     * 获取体态问题列表
     * 
     * @return array 体态问题列表
     */
    public function getPosturalIssuesAttribute(): array
    {
        return $this->health_status['postural_issues'] ?? [];
    }

    /**
     * 检查是否有特定体态问题
     * 
     * @param string $issue 体态问题名称
     * @return bool 是否存在该体态问题
     */
    public function hasPosturalIssue(string $issue): bool
    {
        $posturalIssues = $this->getPosturalIssuesAttribute();
        return in_array($issue, $posturalIssues);
    }
}



