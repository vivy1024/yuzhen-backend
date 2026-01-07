<?php

namespace App\Modules\Exercise\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ExerciseInstruction Model
 * 
 * 动作指导步骤模型
 * 
 * @property int $id
 * @property int $exercise_id
 * @property string $instruction_type (execution|tips|mistakes)
 * @property int $step_number
 * @property string|null $content_en
 * @property string|null $content_zh
 * @property int $display_order
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class ExerciseInstruction extends Model
{
    protected $table = 'exercise_v2_instructions';

    protected $fillable = [
        'exercise_id',
        'instruction_type',
        'step_number',
        'content_en',
        'content_zh',
        'display_order',
    ];

    protected $casts = [
        'exercise_id' => 'integer',
        'step_number' => 'integer',
        'display_order' => 'integer',
    ];

    /**
     * 关联：所属动作
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }

    /**
     * 获取本地化内容
     */
    public function getDisplayContentAttribute(): ?string
    {
        return $this->content_zh ?? $this->content_en;
    }

    /**
     * 判断是否为执行步骤
     */
    public function isExecution(): bool
    {
        return $this->instruction_type === 'execution';
    }

    /**
     * 判断是否为技巧提示
     */
    public function isTips(): bool
    {
        return $this->instruction_type === 'tips';
    }

    /**
     * 判断是否为常见错误
     */
    public function isMistakes(): bool
    {
        return $this->instruction_type === 'mistakes';
    }
}












