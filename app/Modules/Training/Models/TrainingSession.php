<?php

namespace App\Modules\Training\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\User\Models\User;

/**
 * Training Session Model v2.0
 * 
 * 训练会话模型 - 统一字段命名
 * 完全对齐前端 TypeScript 类型定义
 * 
 * @property int $user_id
 * @property int|null $plan_id
 * @property int|null $week
 * @property int|null $day
 * @property string $date
 * @property string $start_time
 * @property string|null $end_time
 * @property array|null $exercises 动作记录
 * @property float|null $total_volume 总容量
 * @property int|null $total_sets 总组数
 * @property float|null $avg_rpe 平均RPE
 * @property string $status
 * @property string|null $notes
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class TrainingSession extends Model
{
    use HasFactory;

    protected $table = 'training_sessions';

    /**
     * 可批量赋值字段
     */
    protected $fillable = [
        'user_id',
        'plan_id',
        'week',
        'day',
        'date',
        'start_time',
        'end_time',
        'exercises',
        'total_volume',
        'total_sets',
        'avg_rpe',
        'status',
        'notes',
        
        // 兼容旧字段（数据迁移期间保留）
        'session_number',
        'name',
        'name_zh',
        'description',
        'estimated_duration',
        'actual_duration',
        'calories_burned',
    ];

    /**
     * 字段类型转换
     */
    protected $casts = [
        'exercises' => 'array',
        'week' => 'integer',
        'day' => 'integer',
        'total_volume' => 'float',
        'total_sets' => 'integer',
        'avg_rpe' => 'float',
        'date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        
        // 兼容旧字段
        'session_number' => 'integer',
        'estimated_duration' => 'integer',
        'actual_duration' => 'integer',
        'calories_burned' => 'integer',
    ];

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联：训练计划
     */
    public function plan()
    {
        return $this->belongsTo(TrainingPlan::class, 'plan_id');
    }

    /**
     * 关联：训练记录
     */
    public function records()
    {
        return $this->hasMany(TrainingRecord::class, 'session_id');
    }

    /**
     * 是否已完成
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * 是否进行中
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in-progress';
    }

    /**
     * 标记为已完成
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'end_time' => now(),
        ]);
        
        // 自动计算统计信息
        $this->calculateStats();
    }

    /**
     * 开始训练
     */
    public function start(): void
    {
        $this->update([
            'status' => 'in-progress',
            'start_time' => now(),
        ]);
    }

    /**
     * 计算统计信息（从exercises JSON数据中）
     */
    public function calculateStats(): void
    {
        if (empty($this->exercises)) {
            return;
        }

        $totalVolume = 0;
        $totalSets = 0;
        $rpeSum = 0;
        $rpeCount = 0;

        foreach ($this->exercises as $exercise) {
            $sets = $exercise['sets'] ?? [];
            
            foreach ($sets as $set) {
                if (!empty($set['completed'])) {
                    $totalSets++;
                    
                    // 计算容量
                    $weight = $set['weight'] ?? 0;
                    $reps = $set['reps'] ?? 0;
                    $totalVolume += $weight * $reps;
                    
                    // 累计RPE
                    if (isset($set['rpe'])) {
                        $rpeSum += $set['rpe'];
                        $rpeCount++;
                    }
                }
            }
        }

        $this->update([
            'total_volume' => $totalVolume,
            'total_sets' => $totalSets,
            'avg_rpe' => $rpeCount > 0 ? round($rpeSum / $rpeCount, 1) : null,
        ]);
    }

    /**
     * 更新动作记录
     */
    public function updateExercises(array $exercises): void
    {
        $this->exercises = $exercises;
        $this->save();
        
        // 重新计算统计信息
        $this->calculateStats();
    }

    /**
     * 获取训练时长（分钟）
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->start_time || !$this->end_time) {
            return null;
        }
        
        return $this->start_time->diffInMinutes($this->end_time);
    }
}

