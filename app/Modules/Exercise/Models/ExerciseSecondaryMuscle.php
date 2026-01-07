<?php

namespace App\Modules\Exercise\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ExerciseSecondaryMuscle Model
 * 
 * 动作次要肌肉群模型
 * 
 * @property int $id
 * @property int $exercise_id
 * @property string $muscle_name
 * @property string|null $muscle_name_zh
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class ExerciseSecondaryMuscle extends Model
{
    protected $table = 'exercise_v2_secondary_muscles';

    protected $fillable = [
        'exercise_id',
        'muscle_name',
        'muscle_name_zh',
    ];

    protected $casts = [
        'exercise_id' => 'integer',
    ];

    /**
     * 关联：所属动作
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }

    /**
     * 获取本地化肌肉名称
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->muscle_name_zh ?? $this->muscle_name;
    }
}












