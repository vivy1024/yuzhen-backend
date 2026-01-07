<?php

namespace App\Modules\Training\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Training Progress Model
 * 
 * 训练进度模型
 */
class TrainingProgress extends Model
{
    use HasFactory;

    protected $table = 'training_progress';

    protected $fillable = [
        'training_plan_id',
        'week_number',
        'completed_workouts',
        'total_duration',
        'total_calories',
        'notes',
    ];

    protected $casts = [
        'week_number' => 'integer',
        'completed_workouts' => 'integer',
        'total_duration' => 'integer',
        'total_calories' => 'integer',
    ];

    /**
     * 关联：训练计划
     */
    public function plan()
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }
}

