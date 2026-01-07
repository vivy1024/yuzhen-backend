<?php

namespace App\Modules\Progress\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

/**
 * Fitness Goal Model
 * 
 * 健身目标模型 - 存储用户的健身目标
 * 
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $name
 * @property float $target_value
 * @property float $current_value
 * @property float $start_value
 * @property string $unit
 * @property string $start_date
 * @property string|null $target_date
 * @property string|null $completed_at
 * @property string $status
 * @property string|null $notes
 */
class FitnessGoal extends Model
{
    use HasFactory;

    protected $table = 'fitness_goals';

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'target_value',
        'current_value',
        'start_value',
        'unit',
        'start_date',
        'target_date',
        'completed_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_date' => 'date',
        'completed_at' => 'date',
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'start_value' => 'decimal:2',
    ];

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 计算进度百分比
     * 
     * @return float 0-100
     */
    public function getProgressAttribute(): float
    {
        $total = abs($this->target_value - $this->start_value);
        if ($total == 0) return 100;
        
        $current = abs($this->current_value - $this->start_value);
        $progress = ($current / $total) * 100;
        
        return min(100, max(0, round($progress, 1)));
    }

    /**
     * 预计完成日期
     * 基于当前进度速率计算
     * 
     * @return string|null
     */
    public function getEstimatedCompletionAttribute(): ?string
    {
        if ($this->status !== 'active') return null;
        if ($this->progress >= 100) return now()->format('Y-m-d');
        
        $daysSinceStart = now()->diffInDays($this->start_date);
        if ($daysSinceStart == 0) return null;
        
        $progressPerDay = $this->progress / $daysSinceStart;
        if ($progressPerDay <= 0) return null;
        
        $remainingProgress = 100 - $this->progress;
        $daysRemaining = ceil($remainingProgress / $progressPerDay);
        
        return now()->addDays($daysRemaining)->format('Y-m-d');
    }

    /**
     * 作用域：活跃目标
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * 作用域：已完成目标
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
