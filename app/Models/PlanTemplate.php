<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PlanTemplate Model - 训练计划模板
 */
class PlanTemplate extends Model
{
    protected $table = 'plan_templates';

    protected $fillable = [
        'name', 'description', 'goal', 'level',
        'duration_weeks', 'workouts_per_week',
        'exercises', 'tags', 'is_active', 'use_count',
    ];

    protected $casts = [
        'exercises' => 'array',
        'tags' => 'array',
        'is_active' => 'boolean',
        'duration_weeks' => 'integer',
        'workouts_per_week' => 'integer',
        'use_count' => 'integer',
    ];
}
