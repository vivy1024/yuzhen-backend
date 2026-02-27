<?php

namespace App\Modules\Training\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Training Record Model
 * 
 * 训练记录模型（单个动作的执行记录）
 */
class TrainingRecord extends Model
{
    use HasFactory;

    protected $table = 'training_records';

    protected $fillable = [
        'session_id',
        'exercise_id',
        'set_number',
        'reps',
        'weight',
        'rpe',
        'rest_seconds',
        'notes',
    ];

    protected $casts = [
        'set_number' => 'integer',
        'reps' => 'integer',
        'weight' => 'float',
        'rpe' => 'float',
        'rest_seconds' => 'integer',
    ];

    /**
     * 关联：训练会话
     */
    public function session()
    {
        return $this->belongsTo(TrainingSession::class, 'session_id');
    }

    /**
     * 关联：动作（通过exercise_id）
     */
    public function exercise()
    {
        return $this->belongsTo(\App\Modules\Exercise\Models\Exercise::class);
    }
}

