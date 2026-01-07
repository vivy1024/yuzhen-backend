<?php

namespace App\Modules\Exercise\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ExerciseTag Model
 * 
 * 动作标签模型
 * 
 * @property int $id
 * @property int $exercise_id
 * @property string $tag_name
 * @property string|null $tag_name_zh
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class ExerciseTag extends Model
{
    protected $table = 'exercise_v2_tags';

    protected $fillable = [
        'exercise_id',
        'tag_name',
        'tag_name_zh',
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
     * 获取本地化标签名称
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->tag_name_zh ?? $this->tag_name;
    }
}












