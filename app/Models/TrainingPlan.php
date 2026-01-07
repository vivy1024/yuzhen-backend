<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrainingPlan Model - 训练计划（扩展版）
 * 
 * 支持AI生成的训练计划导入，包含详细的动作列表、目标肌群和安全提示
 * 
 * @property int $id
 * @property int $user_id 用户ID
 * @property int|null $chat_session_id 来源对话ID
 * @property string $name 计划名称
 * @property string|null $name_zh 计划名称（中文）
 * @property string|null $description 计划描述
 * @property string|null $goal 训练目标
 * @property string|null $difficulty 难度
 * @property int $duration_weeks 总周数
 * @property int $workouts_per_week 每周训练次数
 * @property array|null $exercises 动作列表（AI生成）
 * @property array|null $target_muscles 目标肌群列表
 * @property array|null $safety_notes 安全提示列表
 * @property bool $is_active 是否激活
 * @property string $type 计划来源类型
 * @property \Carbon\Carbon|null $started_at 开始时间
 * @property \Carbon\Carbon|null $completed_at 完成时间
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @version 2.0.0
 * @date 2025-01-02
 */
class TrainingPlan extends Model
{
    use SoftDeletes;
    
    /**
     * 表名
     */
    protected $table = 'training_plans';
    
    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'user_id',
        'chat_session_id',
        'name',
        'name_zh',
        'description',
        'goal',
        'difficulty',
        'duration_weeks',
        'workouts_per_week',
        'exercises',
        'target_muscles',
        'safety_notes',
        'is_active',
        'type',
        'started_at',
        'completed_at',
    ];
    
    /**
     * 属性类型转换
     */
    protected $casts = [
        'exercises' => 'array',
        'target_muscles' => 'array',
        'safety_notes' => 'array',
        'is_active' => 'boolean',
        'duration_weeks' => 'integer',
        'workouts_per_week' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    /**
     * 关联：所属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * 关联：来源对话
     */
    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }
    
    /**
     * 检查是否为AI生成的计划
     */
    public function isAIGenerated(): bool
    {
        return $this->type === 'ai_generated';
    }
    
    /**
     * 检查是否已完成
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
    
    /**
     * 标记为已完成
     */
    public function markAsCompleted(): bool
    {
        return $this->update([
            'completed_at' => now(),
            'is_active' => false,
        ]);
    }
    
    /**
     * 激活计划
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }
    
    /**
     * 停用计划
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }
    
    /**
     * Scope: 按用户筛选
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Scope: 激活的计划
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    /**
     * Scope: AI生成的计划
     */
    public function scopeAIGenerated($query)
    {
        return $query->where('type', 'ai_generated');
    }
    
    /**
     * Scope: 按难度筛选
     */
    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }
    
    /**
     * Scope: 按目标筛选
     */
    public function scopeByGoal($query, string $goal)
    {
        return $query->where('goal', $goal);
    }
}
