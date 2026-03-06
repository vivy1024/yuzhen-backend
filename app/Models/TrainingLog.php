<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\User\Models\User;

/**
 * TrainingLog Model - 训练日志模型
 * 
 * 用于闭环学习系统，记录用户每次训练的详细数据
 * 
 * @property int $id
 * @property int $user_id
 * @property string $session_date
 * @property array $planned_exercises
 * @property array $actual_exercises
 * @property float $completion_rate
 * @property float $avg_rpe
 * @property int $week_number
 * @property string $mesocycle_id
 * @property string $notes
 * 
 * @version 1.0.0
 * @date 2025-12-26
 */
class TrainingLog extends Model
{
    use HasFactory;

    protected $table = 'training_logs';

    protected $fillable = [
        'user_id',
        'training_plan_id',
        'plan_week',
        'plan_day',
        'session_date',
        'planned_exercises',
        'actual_exercises',
        'completion_rate',
        'avg_rpe',
        'week_number',
        'mesocycle_id',
        'notes',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'session_date' => 'date',
        'planned_exercises' => 'array',
        'actual_exercises' => 'array',
        'completion_rate' => 'decimal:2',
        'avg_rpe' => 'decimal:1',
        'week_number' => 'integer',
        'training_plan_id' => 'integer',
        'plan_week' => 'integer',
        'plan_day' => 'integer',
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
     * 关联：训练计划
     */
    public function trainingPlan()
    {
        return $this->belongsTo(\App\Modules\Training\Models\TrainingPlan::class, 'training_plan_id');
    }

    /**
     * 计算完成率
     * 
     * 完成率 = 实际完成组数 / 计划组数
     * 
     * Requirements: 6.3 - 计算该日训练完成率
     * 
     * @return float 完成率（0-1范围内）
     */
    public function calculateCompletionRate(): float
    {
        // 边界情况：没有计划动作
        if (empty($this->planned_exercises)) {
            return 0.0;
        }

        // 边界情况：没有实际完成的动作
        if (empty($this->actual_exercises)) {
            return 0.0;
        }

        $plannedSets = 0;
        $completedSets = 0;

        // 计算计划总组数
        foreach ($this->planned_exercises as $exercise) {
            $sets = $exercise['sets'] ?? 0;
            if ($sets > 0) {
                $plannedSets += $sets;
            }
        }

        // 边界情况：计划组数为0
        if ($plannedSets === 0) {
            return 0.0;
        }

        // 计算实际完成组数
        foreach ($this->actual_exercises as $exercise) {
            $completedSets += $exercise['completed_sets'] ?? 0;
        }

        // 计算完成率，确保结果在0-1范围内
        $rate = $completedSets / $plannedSets;
        
        // 限制在0-1范围内（防止完成超过计划的情况）
        return max(0.0, min(1.0, round($rate, 2)));
    }

    /**
     * 获取计划总组数
     * 
     * @return int 计划总组数
     */
    public function getPlannedSetsCount(): int
    {
        if (empty($this->planned_exercises)) {
            return 0;
        }

        $total = 0;
        foreach ($this->planned_exercises as $exercise) {
            $total += $exercise['sets'] ?? 0;
        }
        return $total;
    }

    /**
     * 获取实际完成组数
     * 
     * @return int 实际完成组数
     */
    public function getCompletedSetsCount(): int
    {
        if (empty($this->actual_exercises)) {
            return 0;
        }

        $total = 0;
        foreach ($this->actual_exercises as $exercise) {
            $total += $exercise['completed_sets'] ?? 0;
        }
        return $total;
    }

    /**
     * 计算平均RPE
     * 
     * @return float 平均RPE（1-10）
     */
    public function calculateAvgRpe(): float
    {
        if (empty($this->actual_exercises)) {
            return 0.0;
        }

        $totalRpe = 0;
        $count = 0;

        foreach ($this->actual_exercises as $exercise) {
            if (isset($exercise['rpe']) && $exercise['rpe'] > 0) {
                // 验证RPE值在有效范围内
                if (self::isValidRpe($exercise['rpe'])) {
                    $totalRpe += $exercise['rpe'];
                    $count++;
                }
            }
        }

        if ($count === 0) {
            return 0.0;
        }

        return round($totalRpe / $count, 1);
    }

    /**
     * 验证RPE值是否在有效范围内
     * 
     * Requirements: 6.2 - RPE值必须在1-10范围内
     * 
     * @param mixed $rpe RPE值
     * @return bool 是否有效
     */
    public static function isValidRpe($rpe): bool
    {
        if (!is_numeric($rpe)) {
            return false;
        }
        
        $rpeValue = (float) $rpe;
        return $rpeValue >= 1.0 && $rpeValue <= 10.0;
    }

    /**
     * 验证RPE值并返回错误信息
     * 
     * Requirements: 6.2 - 超出范围返回验证错误
     * 
     * @param mixed $rpe RPE值
     * @return array|null 错误信息数组，如果有效则返回null
     */
    public static function validateRpe($rpe): ?array
    {
        if ($rpe === null) {
            return null; // RPE是可选的
        }

        if (!is_numeric($rpe)) {
            return [
                'field' => 'rpe',
                'message' => 'RPE值必须是数字',
                'value' => $rpe,
            ];
        }

        $rpeValue = (float) $rpe;
        
        if ($rpeValue < 1.0) {
            return [
                'field' => 'rpe',
                'message' => 'RPE值不能小于1',
                'value' => $rpeValue,
                'min' => 1.0,
                'max' => 10.0,
            ];
        }

        if ($rpeValue > 10.0) {
            return [
                'field' => 'rpe',
                'message' => 'RPE值不能大于10',
                'value' => $rpeValue,
                'min' => 1.0,
                'max' => 10.0,
            ];
        }

        return null;
    }

    /**
     * 规范化RPE值到有效范围
     * 
     * @param mixed $rpe RPE值
     * @return float|null 规范化后的RPE值
     */
    public static function normalizeRpe($rpe): ?float
    {
        if ($rpe === null || !is_numeric($rpe)) {
            return null;
        }

        $rpeValue = (float) $rpe;
        
        // 限制在1-10范围内
        return max(1.0, min(10.0, round($rpeValue, 1)));
    }

    /**
     * 作用域：按用户筛选
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 作用域：按日期范围筛选
     */
    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('session_date', [$startDate, $endDate]);
    }

    /**
     * 作用域：按中周期筛选
     */
    public function scopeForMesocycle($query, string $mesocycleId)
    {
        return $query->where('mesocycle_id', $mesocycleId);
    }
}
