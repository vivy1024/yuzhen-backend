<?php

namespace App\Modules\Exercise\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ExerciseMedia Model
 * 
 * 动作媒体资源模型
 * 
 * @property int $id
 * @property int $exercise_id
 * @property string $media_type (image|video|thumbnail)
 * @property string|null $cdn_url
 * @property string|null $local_path
 * @property int|null $file_size
 * @property int|null $duration
 * @property int $display_order
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class ExerciseMedia extends Model
{
    protected $table = 'exercise_v2_media';

    protected $fillable = [
        'exercise_id',
        'media_type',
        'cdn_url',
        'local_path',
        'file_size',
        'duration',
        'display_order',
    ];

    protected $casts = [
        'exercise_id' => 'integer',
        'file_size' => 'integer',
        'duration' => 'integer',
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
     * 获取完整URL
     */
    public function getFullUrlAttribute(): ?string
    {
        // 优先使用cdn_url
        if ($this->cdn_url) {
            // 如果cdn_url是完整URL（http/https开头），直接返回
            if (str_starts_with($this->cdn_url, 'http://') || str_starts_with($this->cdn_url, 'https://')) {
                return $this->cdn_url;
            }
            // 否则，拼接为完整URL
            return asset('storage/' . $this->cdn_url);
        }

        // 回退到local_path
        if ($this->local_path) {
            return asset('storage/' . $this->local_path);
        }

        return null;
    }

    /**
     * 判断是否为图片
     */
    public function isImage(): bool
    {
        return in_array($this->media_type, ['image', 'thumbnail']);
    }

    /**
     * 判断是否为视频
     */
    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }
}


