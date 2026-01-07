<?php

namespace App\Services;

use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * PersonalizationGradingService - 个性化分级与商业化服务
 * 
 * 功能：
 * 1. 档案利用率计算 (0-100%)
 * 2. 个性化等级划分 (S/A/B/C/D)
 * 3. 升级提示生成
 * 4. 升级收益说明
 * 5. B端演示数据收集
 * 
 * @version 1.0.0
 * @date 2025-12-31
 * @requirements 6.1, 6.2, 6.3, 6.4, 6.5
 */
class PersonalizationGradingService
{
    /**
     * 档案字段权重配置
     * 用于计算档案利用率
     */
    private const PROFILE_FIELD_WEIGHTS = [
        // 基础信息 (20%)
        'basic_info' => [
            'age' => 5,
            'gender' => 5,
            'height' => 5,
            'weight' => 5,
        ],
        // 训练信息 (35%)
        'training_info' => [
            'training_level' => 10,
            'training_goal' => 10,
            'training_frequency' => 8,
            'training_experience' => 7,
        ],
        // 健康信息 (25%)
        'health_info' => [
            'injuries' => 10,
            'health_conditions' => 8,
            'limitations' => 7,
        ],
        // 偏好信息 (20%)
        'preference_info' => [
            'available_equipment' => 8,
            'preferred_exercises' => 6,
            'time_availability' => 6,
        ],
    ];

    /**
     * 个性化等级阈值
     */
    private const GRADE_THRESHOLDS = [
        'S' => 90,  // 90-100%
        'A' => 75,  // 75-89%
        'B' => 60,  // 60-74%
        'C' => 40,  // 40-59%
        'D' => 0,   // 0-39%
    ];

    /**
     * 等级描述
     */
    private const GRADE_DESCRIPTIONS = [
        'S' => [
            'name' => '真个性化',
            'description' => '完全定制，档案利用率>90%',
            'color' => '#ff6b6b',
            'commercial_value' => '精英版价值',
            'features' => ['专家级个性化建议', '完整损伤考虑', '精准器械匹配', '动态调整优化'],
        ],
        'A' => [
            'name' => '高度个性化',
            'description' => '档案利用率75-90%',
            'color' => '#4ecdc4',
            'commercial_value' => '高级版价值',
            'features' => ['高度个性化建议', '损伤风险评估', '器械适配', '目标对齐'],
        ],
        'B' => [
            'name' => '中度个性化',
            'description' => '档案利用率60-75%',
            'color' => '#45b7d1',
            'commercial_value' => '标准版价值',
            'features' => ['基础个性化建议', '通用安全提醒', '基础器械匹配'],
        ],
        'C' => [
            'name' => '基础个性化',
            'description' => '档案利用率40-60%',
            'color' => '#96ceb4',
            'commercial_value' => '免费版标准',
            'features' => ['通用训练建议', '基础安全提示'],
        ],
        'D' => [
            'name' => '个性化不足',
            'description' => '档案利用率<40%',
            'color' => '#dfe6e9',
            'commercial_value' => '需改进',
            'features' => ['通用模板建议'],
        ],
    ];

    /**
     * 计算用户档案利用率
     * 
     * @param User $user 用户对象
     * @param array $sessionMetadata 会话元数据（可选）
     * @return array 包含利用率和详细信息
     * @requirements 6.1
     */
    public function calculateProfileUtilization(User $user, array $sessionMetadata = []): array
    {
        $totalWeight = 0;
        $utilizedWeight = 0;
        $details = [];

        // 获取用户档案数据
        $profile = $this->getUserProfileData($user);

        // 计算各类别的利用率
        foreach (self::PROFILE_FIELD_WEIGHTS as $category => $fields) {
            $categoryTotal = 0;
            $categoryUtilized = 0;
            $categoryDetails = [];

            foreach ($fields as $field => $weight) {
                $categoryTotal += $weight;
                $totalWeight += $weight;

                $isUtilized = $this->isFieldUtilized($profile, $field, $sessionMetadata);
                if ($isUtilized) {
                    $categoryUtilized += $weight;
                    $utilizedWeight += $weight;
                }

                $categoryDetails[$field] = [
                    'weight' => $weight,
                    'utilized' => $isUtilized,
                    'value' => $profile[$field] ?? null,
                ];
            }

            $details[$category] = [
                'total_weight' => $categoryTotal,
                'utilized_weight' => $categoryUtilized,
                'utilization_rate' => $categoryTotal > 0 ? round($categoryUtilized / $categoryTotal * 100, 1) : 0,
                'fields' => $categoryDetails,
            ];
        }

        // 计算总体利用率
        $utilizationRate = $totalWeight > 0 ? round($utilizedWeight / $totalWeight * 100, 1) : 0;

        return [
            'utilization_rate' => $utilizationRate,
            'total_weight' => $totalWeight,
            'utilized_weight' => $utilizedWeight,
            'categories' => $details,
            'profile_completeness' => $this->calculateProfileCompleteness($profile),
        ];
    }

    /**
     * 计算个性化等级
     * 
     * @param float $utilizationRate 档案利用率 (0-100)
     * @return string 等级 (S/A/B/C/D)
     * @requirements 6.2
     */
    public function calculateGrade(float $utilizationRate): string
    {
        if ($utilizationRate >= self::GRADE_THRESHOLDS['S']) return 'S';
        if ($utilizationRate >= self::GRADE_THRESHOLDS['A']) return 'A';
        if ($utilizationRate >= self::GRADE_THRESHOLDS['B']) return 'B';
        if ($utilizationRate >= self::GRADE_THRESHOLDS['C']) return 'C';
        return 'D';
    }

    /**
     * 获取等级详细信息
     * 
     * @param string $grade 等级
     * @return array 等级详细信息
     */
    public function getGradeInfo(string $grade): array
    {
        return self::GRADE_DESCRIPTIONS[$grade] ?? self::GRADE_DESCRIPTIONS['C'];
    }

    /**
     * 生成升级提示
     * 
     * 当用户档案复杂但使用免费版时显示
     * 
     * @param User $user 用户对象
     * @param float $utilizationRate 档案利用率
     * @param string $currentTier 当前会员等级
     * @return array|null 升级提示信息
     * @requirements 6.3
     */
    public function generateUpgradePrompt(User $user, float $utilizationRate, string $currentTier = 'free'): ?array
    {
        // 计算档案复杂度
        $profileCompleteness = $this->calculateProfileCompleteness($this->getUserProfileData($user));
        
        // 如果档案复杂度高但使用免费版
        if ($profileCompleteness >= 70 && $currentTier === 'free') {
            $grade = $this->calculateGrade($utilizationRate);
            
            if (in_array($grade, ['C', 'D'])) {
                return [
                    'show_prompt' => true,
                    'type' => 'profile_underutilized',
                    'title' => '您的档案信息很完整！',
                    'message' => '您已填写了详细的个人档案，但当前版本只能利用其中一部分。升级到高级版可以获得真正个性化的训练建议。',
                    'current_utilization' => $utilizationRate,
                    'potential_utilization' => min($profileCompleteness, 95),
                    'upgrade_benefits' => [
                        '完整利用您的损伤信息，避免受伤风险',
                        '根据您的器械条件定制训练计划',
                        '针对您的目标优化训练强度',
                        '考虑您的恢复能力调整训练频率',
                    ],
                    'recommended_tier' => $profileCompleteness >= 85 ? 'elite' : 'premium',
                ];
            }
        }

        return null;
    }

    /**
     * 生成升级收益说明
     * 
     * 当用户频繁使用但个性化程度低时显示
     * 
     * @param User $user 用户对象
     * @param int $usageCount 使用次数
     * @param float $avgPersonalization 平均个性化得分
     * @return array|null 升级收益说明
     * @requirements 6.4
     */
    public function generateUpgradeBenefits(User $user, int $usageCount, float $avgPersonalization): ?array
    {
        // 频繁使用（>10次）但个性化低（<60%）
        if ($usageCount >= 10 && $avgPersonalization < 60) {
            $currentGrade = $this->calculateGrade($avgPersonalization);
            $potentialGrade = $this->estimatePotentialGrade($user);

            return [
                'show_benefits' => true,
                'type' => 'frequent_user_low_personalization',
                'title' => '让您的训练更有针对性',
                'message' => sprintf(
                    '您已使用智能顾问%d次，但当前个性化程度仅为%s级。升级后可达到%s级个性化。',
                    $usageCount,
                    $currentGrade,
                    $potentialGrade
                ),
                'current_stats' => [
                    'usage_count' => $usageCount,
                    'current_grade' => $currentGrade,
                    'current_personalization' => $avgPersonalization,
                ],
                'potential_stats' => [
                    'potential_grade' => $potentialGrade,
                    'potential_personalization' => $this->estimatePotentialUtilization($user),
                ],
                'value_comparison' => [
                    'free' => [
                        'name' => '免费版',
                        'personalization' => '基础个性化',
                        'features' => ['通用训练建议', '基础安全提示'],
                    ],
                    'premium' => [
                        'name' => '高级版',
                        'personalization' => '高度个性化',
                        'features' => ['损伤风险评估', '器械适配', '目标对齐', '进度追踪'],
                        'price' => '¥29/月',
                    ],
                    'elite' => [
                        'name' => '精英版',
                        'personalization' => '真个性化',
                        'features' => ['专家级建议', '完整损伤考虑', '精准器械匹配', '动态调整', '专属客服'],
                        'price' => '¥99/月',
                    ],
                ],
            ];
        }

        return null;
    }

    /**
     * 收集B端演示数据
     * 
     * @param array $filters 过滤条件
     * @return array 演示数据统计
     * @requirements 6.5
     */
    public function collectDemoData(array $filters = []): array
    {
        $query = ChatSession::query();

        // 应用时间过滤
        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        // 获取基础统计
        $totalSessions = $query->count();
        
        // 个性化等级分布
        $gradeDistribution = ChatSession::selectRaw('personalization_grade, COUNT(*) as count')
            ->whereNotNull('personalization_grade')
            ->groupBy('personalization_grade')
            ->pluck('count', 'personalization_grade')
            ->toArray();

        // 档案利用率统计
        $utilizationStats = ChatSession::selectRaw('
            AVG(profile_utilization_rate) as avg_utilization,
            MIN(profile_utilization_rate) as min_utilization,
            MAX(profile_utilization_rate) as max_utilization,
            STDDEV(profile_utilization_rate) as std_utilization
        ')
            ->whereNotNull('profile_utilization_rate')
            ->first();

        // 按等级统计平均利用率
        $utilizationByGrade = ChatSession::selectRaw('
            personalization_grade,
            AVG(profile_utilization_rate) as avg_utilization,
            COUNT(*) as session_count
        ')
            ->whereNotNull('personalization_grade')
            ->whereNotNull('profile_utilization_rate')
            ->groupBy('personalization_grade')
            ->get()
            ->keyBy('personalization_grade')
            ->toArray();

        // 用户分布统计
        $userStats = $this->collectUserStats();

        // 趋势数据（最近30天）
        $trendData = $this->collectTrendData(30);

        // 商业价值指标
        $commercialMetrics = $this->calculateCommercialMetrics();

        return [
            'summary' => [
                'total_sessions' => $totalSessions,
                'avg_utilization_rate' => round($utilizationStats->avg_utilization ?? 0, 1),
                'high_personalization_rate' => $this->calculateHighPersonalizationRate($gradeDistribution, $totalSessions),
            ],
            'grade_distribution' => [
                'S' => $gradeDistribution['S'] ?? 0,
                'A' => $gradeDistribution['A'] ?? 0,
                'B' => $gradeDistribution['B'] ?? 0,
                'C' => $gradeDistribution['C'] ?? 0,
                'D' => $gradeDistribution['D'] ?? 0,
            ],
            'utilization_stats' => [
                'average' => round($utilizationStats->avg_utilization ?? 0, 1),
                'min' => round($utilizationStats->min_utilization ?? 0, 1),
                'max' => round($utilizationStats->max_utilization ?? 0, 1),
                'std_deviation' => round($utilizationStats->std_utilization ?? 0, 2),
            ],
            'utilization_by_grade' => $utilizationByGrade,
            'user_stats' => $userStats,
            'trend_data' => $trendData,
            'commercial_metrics' => $commercialMetrics,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * 获取用户的个性化分级报告
     * 
     * @param User $user 用户对象
     * @return array 完整的个性化分级报告
     */
    public function getUserGradingReport(User $user): array
    {
        // 计算档案利用率
        $utilizationData = $this->calculateProfileUtilization($user);
        $utilizationRate = $utilizationData['utilization_rate'];
        
        // 计算等级
        $grade = $this->calculateGrade($utilizationRate);
        $gradeInfo = $this->getGradeInfo($grade);

        // 获取用户会话统计
        $sessionStats = $this->getUserSessionStats($user->id);

        // 生成升级提示
        $currentTier = $user->membership_tier ?? 'free';
        $upgradePrompt = $this->generateUpgradePrompt($user, $utilizationRate, $currentTier);

        // 生成升级收益说明
        $upgradeBenefits = $this->generateUpgradeBenefits(
            $user,
            $sessionStats['total_sessions'],
            $sessionStats['avg_personalization']
        );

        return [
            'user_id' => $user->id,
            'utilization' => $utilizationData,
            'grade' => [
                'level' => $grade,
                'name' => $gradeInfo['name'],
                'description' => $gradeInfo['description'],
                'color' => $gradeInfo['color'],
                'commercial_value' => $gradeInfo['commercial_value'],
                'features' => $gradeInfo['features'],
            ],
            'session_stats' => $sessionStats,
            'upgrade_prompt' => $upgradePrompt,
            'upgrade_benefits' => $upgradeBenefits,
            'recommendations' => $this->generateRecommendations($utilizationData, $grade),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    // ============ 私有辅助方法 ============

    /**
     * 获取用户档案数据
     */
    private function getUserProfileData(User $user): array
    {
        $profile = $user->profile ?? [];
        
        return [
            // 基础信息
            'age' => $user->age ?? $profile['age'] ?? null,
            'gender' => $user->gender ?? $profile['gender'] ?? null,
            'height' => $user->height ?? $profile['height'] ?? null,
            'weight' => $user->weight ?? $profile['weight'] ?? null,
            // 训练信息
            'training_level' => $profile['training_level'] ?? null,
            'training_goal' => $profile['training_goal'] ?? null,
            'training_frequency' => $profile['training_frequency'] ?? null,
            'training_experience' => $profile['training_experience'] ?? null,
            // 健康信息
            'injuries' => $profile['injuries'] ?? null,
            'health_conditions' => $profile['health_conditions'] ?? null,
            'limitations' => $profile['limitations'] ?? null,
            // 偏好信息
            'available_equipment' => $profile['available_equipment'] ?? null,
            'preferred_exercises' => $profile['preferred_exercises'] ?? null,
            'time_availability' => $profile['time_availability'] ?? null,
        ];
    }

    /**
     * 检查字段是否被利用
     */
    private function isFieldUtilized(array $profile, string $field, array $sessionMetadata): bool
    {
        // 首先检查字段是否有值
        $hasValue = !empty($profile[$field]);
        
        if (!$hasValue) {
            return false;
        }

        // 检查会话元数据中是否使用了该字段
        $usedFields = $sessionMetadata['used_profile_fields'] ?? [];
        if (in_array($field, $usedFields)) {
            return true;
        }

        // 检查工具使用情况
        $toolsUsed = $sessionMetadata['tools_used'] ?? [];
        $fieldToolMapping = [
            'injuries' => ['contraindications_checker', 'injury_risk_assessor', 'safe_exercise_modifier'],
            'available_equipment' => ['intelligent_exercise_selector', 'exercise_alternative_finder'],
            'training_goal' => ['training_goal_recommender', 'professional_program_designer'],
            'training_level' => ['intelligent_weight_calculator', 'professional_program_designer'],
        ];

        if (isset($fieldToolMapping[$field])) {
            foreach ($fieldToolMapping[$field] as $tool) {
                if (in_array($tool, $toolsUsed)) {
                    return true;
                }
            }
        }

        // 默认：如果有值就认为被利用（基础利用）
        return $hasValue;
    }

    /**
     * 计算档案完整度
     */
    private function calculateProfileCompleteness(array $profile): float
    {
        $totalFields = 0;
        $filledFields = 0;

        foreach (self::PROFILE_FIELD_WEIGHTS as $category => $fields) {
            foreach ($fields as $field => $weight) {
                $totalFields++;
                if (!empty($profile[$field])) {
                    $filledFields++;
                }
            }
        }

        return $totalFields > 0 ? round($filledFields / $totalFields * 100, 1) : 0;
    }

    /**
     * 估算潜在等级
     */
    private function estimatePotentialGrade(User $user): string
    {
        $profile = $this->getUserProfileData($user);
        $completeness = $this->calculateProfileCompleteness($profile);
        
        // 假设升级后可以利用90%的已填写档案
        $potentialUtilization = $completeness * 0.9;
        
        return $this->calculateGrade($potentialUtilization);
    }

    /**
     * 估算潜在利用率
     */
    private function estimatePotentialUtilization(User $user): float
    {
        $profile = $this->getUserProfileData($user);
        $completeness = $this->calculateProfileCompleteness($profile);
        
        return min($completeness * 0.9, 95);
    }

    /**
     * 获取用户会话统计
     */
    private function getUserSessionStats(int $userId): array
    {
        $sessions = ChatSession::where('user_id', $userId);
        
        return [
            'total_sessions' => $sessions->count(),
            'avg_personalization' => round($sessions->avg('profile_utilization_rate') ?? 0, 1),
            'avg_ux_score' => round($sessions->avg('user_ux_score') ?? 0, 2),
            'fewshot_eligible_count' => $sessions->where('fewshot_eligible', true)->count(),
        ];
    }

    /**
     * 收集用户统计
     */
    private function collectUserStats(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::whereHas('chatSessions', function ($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            })->count(),
            'users_with_complete_profile' => User::whereNotNull('profile')
                ->whereRaw("JSON_LENGTH(profile) > 5")
                ->count(),
        ];
    }

    /**
     * 收集趋势数据
     */
    private function collectTrendData(int $days): array
    {
        return ChatSession::selectRaw('
            DATE(created_at) as date,
            AVG(profile_utilization_rate) as avg_utilization,
            COUNT(*) as session_count
        ')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('profile_utilization_rate')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * 计算商业价值指标
     */
    private function calculateCommercialMetrics(): array
    {
        $totalSessions = ChatSession::count();
        $highPersonalization = ChatSession::whereIn('personalization_grade', ['S', 'A'])->count();
        
        return [
            'conversion_potential' => $totalSessions > 0 
                ? round(($totalSessions - $highPersonalization) / $totalSessions * 100, 1) 
                : 0,
            'upgrade_candidates' => ChatSession::where('profile_utilization_rate', '>=', 60)
                ->whereIn('personalization_grade', ['C', 'D'])
                ->distinct('user_id')
                ->count('user_id'),
            'high_value_users' => ChatSession::whereIn('personalization_grade', ['S', 'A'])
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }

    /**
     * 计算高个性化率
     */
    private function calculateHighPersonalizationRate(array $distribution, int $total): float
    {
        if ($total === 0) return 0;
        
        $highCount = ($distribution['S'] ?? 0) + ($distribution['A'] ?? 0);
        return round($highCount / $total * 100, 1);
    }

    /**
     * 生成改进建议
     */
    private function generateRecommendations(array $utilizationData, string $grade): array
    {
        $recommendations = [];

        foreach ($utilizationData['categories'] as $category => $data) {
            if ($data['utilization_rate'] < 50) {
                $categoryNames = [
                    'basic_info' => '基础信息',
                    'training_info' => '训练信息',
                    'health_info' => '健康信息',
                    'preference_info' => '偏好信息',
                ];

                $recommendations[] = [
                    'category' => $category,
                    'category_name' => $categoryNames[$category] ?? $category,
                    'current_rate' => $data['utilization_rate'],
                    'suggestion' => sprintf(
                        '完善您的%s可以提升个性化程度约%d%%',
                        $categoryNames[$category] ?? $category,
                        round((100 - $data['utilization_rate']) * 0.3)
                    ),
                ];
            }
        }

        return $recommendations;
    }
}
