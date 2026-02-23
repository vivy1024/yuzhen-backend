<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChatSession Model - AI对话历史
 * 
 * 存储用户与AI的对话记录，支持：
 * 1. 多轮对话管理（session_id）
 * 2. 匿名用户对话（user_id可为NULL）
 * 3. 向量检索关联（qdrant_point_id）
 * 4. 质量评估（user_rating, user_feedback）
 * 5. 三轨评分体系（用户体验、个性化感知、综合评分）
 * 
 * 使用场景：
 * - MCO服务记录对话历史
 * - Few-Shot学习检索历史优质对话
 * - 用户历史对话查询
 * - 对话质量分析
 * - 三轨评分筛选高质量数据
 * 
 * @property int $id
 * @property string $session_id 会话UUID
 * @property int|null $user_id 用户ID
 * @property string $user_query 用户问题
 * @property string $llm_response AI回答
 * @property string $model_used 使用的模型
 * @property array|null $tools_used 调用的工具列表
 * @property array|null $metadata 元数据
 * @property int|null $user_rating 用户评分（1-5星）
 * @property string|null $user_feedback 用户反馈
 * @property int|null $ux_clarity 易懂性评分（1-5）
 * @property int|null $ux_practicality 实用性评分（1-5）
 * @property int|null $ux_detail 详细程度评分（1-5）
 * @property int|null $ux_friendliness 友好度评分（1-5）
 * @property int|null $ux_satisfaction 整体满意度评分（1-5）
 * @property float|null $profile_utilization_rate 档案利用率（0-100%）
 * @property float|null $goal_alignment 目标对齐度（0-100%）
 * @property float|null $uniqueness 独特性（0-100%）
 * @property float|null $dynamic_adjustment 动态调整（0-100%）
 * @property string|null $personalization_grade 个性化等级（S/A/B/C/D）
 * @property bool $fewshot_eligible 是否符合Few-Shot条件
 * @property float|null $overall_score 综合评分（0-5）
 * @property string|null $qdrant_point_id Qdrant向量点ID
 * @property int|null $ttfb_ms 首字节时间(毫秒)
 * @property int|null $duration_ms 总耗时(毫秒)
 * @property float|null $tokens_per_sec 令牌生成速率
 * @property string|null $backend_used 实际后端
 * @property string $execution_mode 执行模式(dag/agent)
 * @property string|null $template_name 模板名称
 * @property int $input_tokens 输入Token数
 * @property int $output_tokens 输出Token数
 * @property float|null $estimated_cost 估算费用(美元)
 * @property int $credits_consumed 消耗积分
 * @property int $fallback_count 降级次数
 * @property string|null $error_type 错误类型
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * 
 * @version 2.0.0
 * @date 2025-12-31
 */
class ChatSession extends Model
{
    use HasFactory;

    /**
     * 表名
     */
    protected $table = 'chat_sessions';

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'session_id',
        'user_id',
        'topic_id',
        'user_query',
        'llm_response',
        'model_used',
        'tools_used',
        'metadata',
        'user_rating',
        'user_feedback',
        'qdrant_point_id',
        // 用户体验评分（5维度）
        'ux_clarity',
        'ux_practicality',
        'ux_detail',
        'ux_friendliness',
        'ux_satisfaction',
        // 个性化感知评分（4维度）
        'profile_utilization_rate',
        'goal_alignment',
        'uniqueness',
        'dynamic_adjustment',
        // 综合评分
        'personalization_grade',
        'fewshot_eligible',
        'overall_score',
        // 训练效果标签 @requirements 4.4
        'training_effect',
        // 性能监控字段（unified-observability-dashboard）
        'ttfb_ms',
        'duration_ms',
        'tokens_per_sec',
        'backend_used',
        'execution_mode',
        'template_name',
        'input_tokens',
        'output_tokens',
        'estimated_cost',
        'credits_consumed',
        'fallback_count',
        'error_type',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'tools_used' => 'array',
        'metadata' => 'array',
        'user_rating' => 'integer',
        // 用户体验评分
        'ux_clarity' => 'integer',
        'ux_practicality' => 'integer',
        'ux_detail' => 'integer',
        'ux_friendliness' => 'integer',
        'ux_satisfaction' => 'integer',
        // 个性化感知评分
        'profile_utilization_rate' => 'decimal:2',
        'goal_alignment' => 'decimal:2',
        'uniqueness' => 'decimal:2',
        'dynamic_adjustment' => 'decimal:2',
        // 综合评分
        'fewshot_eligible' => 'boolean',
        'overall_score' => 'decimal:2',
        // 性能监控字段
        'ttfb_ms' => 'integer',
        'duration_ms' => 'integer',
        'tokens_per_sec' => 'decimal:2',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'estimated_cost' => 'decimal:4',
        'credits_consumed' => 'integer',
        'fallback_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联：所属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * 关联：所属话题
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ChatTopic::class, 'topic_id');
    }
    
    /**
     * 关联：生成的训练计划
     */
    public function trainingPlans()
    {
        return $this->hasMany(TrainingPlan::class);
    }

    /**
     * 获取同一会话的所有对话（多轮对话）
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getConversationHistory()
    {
        return self::where('session_id', $this->session_id)
                   ->orderBy('created_at', 'asc')
                   ->get();
    }

    /**
     * 检查是否为高质量对话（有用户好评）
     * 
     * @return bool
     */
    public function isHighQuality(): bool
    {
        return $this->user_rating !== null && $this->user_rating >= 4;
    }

    /**
     * 检查是否符合Few-Shot条件（三轨高分）
     * 
     * 条件：
     * 1. 用户体验评分平均值 ≥ 4.0
     * 2. 个性化感知评分平均值 ≥ 4.0（转换为5分制）
     * 3. 专家专业评分平均值 ≥ 4.0（需要关联expert_reviews表）
     * 4. 安全性评分 ≥ 3（否则一票否决）
     * 
     * @return bool
     */
    public function checkFewShotEligibility(): bool
    {
        // 计算用户体验评分平均值
        $uxScores = array_filter([
            $this->ux_clarity,
            $this->ux_practicality,
            $this->ux_detail,
            $this->ux_friendliness,
            $this->ux_satisfaction,
        ], fn($v) => $v !== null);
        
        if (empty($uxScores)) {
            return false;
        }
        
        $uxAvg = array_sum($uxScores) / count($uxScores);
        
        // 计算个性化感知评分平均值（转换为5分制：100% = 5分）
        $personalizationScores = array_filter([
            $this->profile_utilization_rate,
            $this->goal_alignment,
            $this->uniqueness,
            $this->dynamic_adjustment,
        ], fn($v) => $v !== null);
        
        if (empty($personalizationScores)) {
            return false;
        }
        
        $personalizationAvg = (array_sum($personalizationScores) / count($personalizationScores)) / 20; // 100% -> 5分
        
        // 检查专家评分（如果有）
        $expertReview = $this->expertReviews()->first();
        if ($expertReview) {
            // 安全性一票否决
            if ($expertReview->safety < 3) {
                return false;
            }
            
            $expertAvg = ($expertReview->accuracy + $expertReview->scientific + 
                         $expertReview->safety + $expertReview->completeness + 
                         $expertReview->practicality + $expertReview->personalization) / 6;
            
            return $uxAvg >= 4.0 && $personalizationAvg >= 4.0 && $expertAvg >= 4.0;
        }
        
        // 没有专家评分时，只检查用户体验和个性化感知
        return $uxAvg >= 4.0 && $personalizationAvg >= 4.0;
    }

    /**
     * 计算综合评分
     * 
     * @return float|null
     */
    public function calculateOverallScore(): ?float
    {
        $scores = [];
        
        // 用户体验评分平均值
        $uxScores = array_filter([
            $this->ux_clarity,
            $this->ux_practicality,
            $this->ux_detail,
            $this->ux_friendliness,
            $this->ux_satisfaction,
        ], fn($v) => $v !== null);
        
        if (!empty($uxScores)) {
            $scores[] = array_sum($uxScores) / count($uxScores);
        }
        
        // 个性化感知评分平均值（转换为5分制）
        $personalizationScores = array_filter([
            $this->profile_utilization_rate,
            $this->goal_alignment,
            $this->uniqueness,
            $this->dynamic_adjustment,
        ], fn($v) => $v !== null);
        
        if (!empty($personalizationScores)) {
            $scores[] = (array_sum($personalizationScores) / count($personalizationScores)) / 20;
        }
        
        // 专家评分平均值
        $expertReview = $this->expertReviews()->first();
        if ($expertReview) {
            $scores[] = ($expertReview->accuracy + $expertReview->scientific + 
                        $expertReview->safety + $expertReview->completeness + 
                        $expertReview->practicality + $expertReview->personalization) / 6;
        }
        
        return empty($scores) ? null : round(array_sum($scores) / count($scores), 2);
    }

    /**
     * 根据档案利用率计算个性化等级
     * 
     * @return string|null
     */
    public function calculatePersonalizationGrade(): ?string
    {
        if ($this->profile_utilization_rate === null) {
            return null;
        }
        
        $rate = $this->profile_utilization_rate;
        
        if ($rate >= 90) return 'S';
        if ($rate >= 75) return 'A';
        if ($rate >= 60) return 'B';
        if ($rate >= 40) return 'C';
        return 'D';
    }

    /**
     * 更新三轨评分相关字段
     * 
     * @return bool
     */
    public function updateThreeTrackRating(): bool
    {
        $this->personalization_grade = $this->calculatePersonalizationGrade();
        $this->overall_score = $this->calculateOverallScore();
        $this->fewshot_eligible = $this->checkFewShotEligibility();
        
        return $this->save();
    }

    /**
     * 获取用户体验评分数组
     * 
     * @return array
     */
    public function getUserExperienceScores(): array
    {
        return [
            'clarity' => $this->ux_clarity,
            'practicality' => $this->ux_practicality,
            'detail' => $this->ux_detail,
            'friendliness' => $this->ux_friendliness,
            'satisfaction' => $this->ux_satisfaction,
        ];
    }

    /**
     * 获取个性化感知评分数组
     * 
     * @return array
     */
    public function getPersonalizationScores(): array
    {
        return [
            'profile_utilization_rate' => $this->profile_utilization_rate,
            'goal_alignment' => $this->goal_alignment,
            'uniqueness' => $this->uniqueness,
            'dynamic_adjustment' => $this->dynamic_adjustment,
        ];
    }

    /**
     * 关联：专家评审
     */
    public function expertReviews()
    {
        return $this->hasMany(ExpertReview::class, 'chat_session_id');
    }

    /**
     * 获取工具使用统计
     * 
     * @return array
     */
    public function getToolsStats(): array
    {
        $tools = $this->tools_used ?? [];
        return [
            'count' => count($tools),
            'tools' => $tools,
            'has_orchestrator' => $this->metadata['orchestrator_used'] ?? false,
        ];
    }

    /**
     * Scope: 按用户筛选
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: 按会话筛选
     */
    public function scopeBySession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Scope: 高质量对话（用于Few-Shot学习）
     */
    public function scopeHighQuality($query)
    {
        return $query->where('user_rating', '>=', 4);
    }

    /**
     * Scope: Few-Shot合格对话（三轨高分）
     */
    public function scopeFewShotEligible($query)
    {
        return $query->where('fewshot_eligible', true);
    }

    /**
     * Scope: 按个性化等级筛选
     */
    public function scopeByPersonalizationGrade($query, string $grade)
    {
        return $query->where('personalization_grade', $grade);
    }

    /**
     * Scope: 高个性化对话（S或A级）
     */
    public function scopeHighPersonalization($query)
    {
        return $query->whereIn('personalization_grade', ['S', 'A']);
    }

    /**
     * Scope: 按模型筛选
     */
    public function scopeByModel($query, string $model)
    {
        return $query->where('model_used', $model);
    }

    /**
     * Scope: 最近的对话
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: 按实际后端筛选
     */
    public function scopeByBackend($query, string $backend)
    {
        return $query->where('backend_used', $backend);
    }

    /**
     * Scope: 按执行模式筛选
     */
    public function scopeByMode($query, string $mode)
    {
        return $query->where('execution_mode', $mode);
    }

    /**
     * Scope: 只查有性能数据的记录
     */
    public function scopeWithPerformance($query)
    {
        return $query->whereNotNull('ttfb_ms');
    }

    /**
     * 更新性能监控字段
     *
     * 由 InternalCreditController::record() 调用，
     * 数据来源：stream_executor → credit_reporter → 后端
     */
    public function updatePerformanceMetrics(array $data): bool
    {
        $allowed = [
            'ttfb_ms', 'duration_ms', 'tokens_per_sec',
            'backend_used', 'execution_mode', 'template_name',
            'input_tokens', 'output_tokens', 'estimated_cost',
            'credits_consumed', 'fallback_count', 'error_type',
        ];

        $filtered = array_intersect_key($data, array_flip($allowed));

        if (empty($filtered)) {
            return false;
        }

        return $this->update($filtered);
    }
}

































