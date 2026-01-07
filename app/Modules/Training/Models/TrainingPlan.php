<?php

namespace App\Modules\Training\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\User\Models\User;

/**
 * Training Plan Model v2.0
 * 
 * 训练计划模型 - 支持周期化结构
 * 完全对齐前端 TypeScript 类型定义
 * 
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property string $type template/ai_generated/manual
 * @property int|null $source_template_id
 * @property array|null $phases 周期化阶段
 * @property array|null $weekly_schedule 周训练安排
 * @property int|null $total_weeks 总周数
 * @property int|null $total_sessions 总训练次数
 * @property array|null $stats 统计信息
 * @property string $status active/completed/archived
 * @property bool $is_active 是否为当前使用计划
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class TrainingPlan extends Model
{
    use HasFactory;

    protected $table = 'training_plans';

    /**
     * 可批量赋值字段
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'type',
        'source_template_id',
        'phases',
        'weekly_schedule',
        'total_weeks',
        'total_sessions',
        'stats',
        'status',
        'is_active',
        
        // 兼容旧字段（数据迁移期间保留）
        'name_zh',
        'goal',
        'difficulty',
        'duration_weeks',
        'workouts_per_week',
        'started_at',
        'completed_at',
    ];

    /**
     * 字段类型转换
     */
    protected $casts = [
        'phases' => 'array',
        'weekly_schedule' => 'array',
        'stats' => 'array',
        'total_weeks' => 'integer',
        'total_sessions' => 'integer',
        'is_active' => 'boolean',
        
        // 兼容旧字段
        'duration_weeks' => 'integer',
        'workouts_per_week' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联：训练会话
     */
    public function sessions()
    {
        return $this->hasMany(TrainingSession::class, 'plan_id');
    }

    /**
     * 关联：训练进度
     */
    public function progress()
    {
        return $this->hasMany(TrainingProgress::class);
    }

    /**
     * 关联：训练日志
     */
    public function trainingLogs()
    {
        return $this->hasMany(\App\Models\TrainingLog::class, 'training_plan_id');
    }

    /**
     * 计算完成百分比
     */
    public function getCompletionPercentageAttribute(): int
    {
        // 优先使用stats中的数据
        if (isset($this->stats['completion_rate'])) {
            return $this->stats['completion_rate'];
        }
        
        // 回退计算
        $totalSessions = $this->total_sessions ?? $this->sessions()->count();
        if ($totalSessions === 0) {
            return 0;
        }
        
        $completedSessions = $this->stats['completed_sessions'] 
            ?? $this->sessions()->where('status', 'completed')->count();
        
        return (int) round(($completedSessions / $totalSessions) * 100);
    }

    /**
     * 是否已完成
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed' || $this->completed_at !== null;
    }

    /**
     * 是否为AI生成的计划
     */
    public function isAiGenerated(): bool
    {
        return $this->type === 'ai_generated';
    }

    /**
     * 是否基于模板
     */
    public function isFromTemplate(): bool
    {
        return $this->type === 'template' && $this->source_template_id !== null;
    }

    /**
     * 获取当前阶段索引
     */
    public function getCurrentPhaseIndex(): int
    {
        return $this->stats['current_phase_index'] ?? 0;
    }

    /**
     * 获取当前周索引
     */
    public function getCurrentWeekIndex(): int
    {
        return $this->stats['current_week_index'] ?? 0;
    }

    /**
     * 更新统计信息
     */
    public function updateStats(array $newStats): void
    {
        $currentStats = $this->stats ?? [];
        $this->stats = array_merge($currentStats, $newStats);
        $this->save();
    }

    /**
     * 标记为活跃计划（同时将其他计划设为非活跃）
     */
    public function markAsActive(): void
    {
        // 将当前用户的其他计划设为非活跃
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);
        
        // 设置当前计划为活跃
        $this->update([
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    /**
     * 标记为已完成
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'is_active' => false,
            'completed_at' => now(),
        ]);
    }

    /**
     * 归档计划
     */
    public function archive(): void
    {
        $this->update([
            'status' => 'archived',
            'is_active' => false,
        ]);
    }
}

