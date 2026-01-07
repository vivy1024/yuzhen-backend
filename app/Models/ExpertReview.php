<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ExpertReview Model - 专家评审记录
 * 
 * 存储专家对AI对话的专业评分（6维度）：
 * 1. 专业准确性：健身知识是否准确
 * 2. 科学合理性：建议是否符合运动科学
 * 3. 安全性：建议是否安全（一票否决）
 * 4. 完整性：回答是否完整全面
 * 5. 实用性：建议是否可执行
 * 6. 个性化适配度：是否针对用户情况定制
 * 
 * 使用场景：
 * - 专家审核AI对话质量
 * - Few-Shot学习数据筛选
 * - 对话质量分析和改进
 * 
 * @property int $id
 * @property int $chat_session_id 关联的对话会话ID
 * @property int $expert_id 评审专家的用户ID
 * @property int $accuracy 专业准确性（1-5）
 * @property int $scientific 科学合理性（1-5）
 * @property int $safety 安全性（1-5）
 * @property int $completeness 完整性（1-5）
 * @property int $practicality 实用性（1-5）
 * @property int $personalization 个性化适配度（1-5）
 * @property string|null $comments 评审意见
 * @property array|null $improvement_suggestions 改进建议
 * @property \Carbon\Carbon $reviewed_at 评审时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * 
 * @version 1.0.0
 * @date 2025-12-31
 */
class ExpertReview extends Model
{
    use HasFactory;

    /**
     * 表名
     */
    protected $table = 'expert_reviews';

    /**
     * 禁用默认的created_at字段
     */
    const CREATED_AT = 'reviewed_at';

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'chat_session_id',
        'expert_id',
        'accuracy',
        'scientific',
        'safety',
        'completeness',
        'practicality',
        'personalization',
        'comments',
        'improvement_suggestions',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'accuracy' => 'integer',
        'scientific' => 'integer',
        'safety' => 'integer',
        'completeness' => 'integer',
        'practicality' => 'integer',
        'personalization' => 'integer',
        'improvement_suggestions' => 'array',
        'reviewed_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联：所属对话会话
     */
    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    /**
     * 关联：评审专家
     */
    public function expert(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expert_id');
    }

    /**
     * 计算专家评分平均值
     * 
     * @return float
     */
    public function getAverageScore(): float
    {
        return round(($this->accuracy + $this->scientific + $this->safety + 
                     $this->completeness + $this->practicality + $this->personalization) / 6, 2);
    }

    /**
     * 检查是否通过安全性审核
     * 安全性评分 < 3 则一票否决
     * 
     * @return bool
     */
    public function passesSafetyCheck(): bool
    {
        return $this->safety >= 3;
    }

    /**
     * 检查是否为高质量评审（平均分 >= 4.0）
     * 
     * @return bool
     */
    public function isHighQuality(): bool
    {
        return $this->passesSafetyCheck() && $this->getAverageScore() >= 4.0;
    }

    /**
     * 获取所有评分数组
     * 
     * @return array
     */
    public function getAllScores(): array
    {
        return [
            'accuracy' => $this->accuracy,
            'scientific' => $this->scientific,
            'safety' => $this->safety,
            'completeness' => $this->completeness,
            'practicality' => $this->practicality,
            'personalization' => $this->personalization,
            'average' => $this->getAverageScore(),
        ];
    }

    /**
     * 获取评分摘要
     * 
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'scores' => $this->getAllScores(),
            'passes_safety' => $this->passesSafetyCheck(),
            'is_high_quality' => $this->isHighQuality(),
            'comments' => $this->comments,
            'suggestions' => $this->improvement_suggestions,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }

    /**
     * Scope: 按专家筛选
     */
    public function scopeByExpert($query, int $expertId)
    {
        return $query->where('expert_id', $expertId);
    }

    /**
     * Scope: 高质量评审
     */
    public function scopeHighQuality($query)
    {
        return $query->where('safety', '>=', 3)
                     ->whereRaw('(accuracy + scientific + safety + completeness + practicality + personalization) / 6 >= 4.0');
    }

    /**
     * Scope: 安全性不合格
     */
    public function scopeUnsafe($query)
    {
        return $query->where('safety', '<', 3);
    }

    /**
     * Scope: 最近的评审
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('reviewed_at', '>=', now()->subDays($days));
    }
}
