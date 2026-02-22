<?php

namespace App\Models;

use App\Modules\Exercise\Models\Exercise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrainingPlanExercise Model - 训练计划动作
 *
 * @property int $id
 * @property int $plan_id 训练计划ID
 * @property int|null $exercise_id 动作ID
 * @property string $exercise_name 动作名称
 * @property int|null $day_of_week 星期几(1-7)
 * @property int $sets 组数
 * @property string $reps 次数范围
 * @property string|null $weight 建议重量
 * @property string $rest_time 组间休息
 * @property string|null $notes 备注
 * @property int $order_index 排序索引
 */
class TrainingPlanExercise extends Model
{
    protected $table = 'training_plan_exercises';

    protected $fillable = [
        'plan_id',
        'exercise_id',
        'exercise_name',
        'day_of_week',
        'sets',
        'reps',
        'weight',
        'rest_time',
        'notes',
        'order_index',
    ];

    protected $casts = [
        'plan_id' => 'integer',
        'exercise_id' => 'integer',
        'day_of_week' => 'integer',
        'sets' => 'integer',
        'order_index' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class, 'plan_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
