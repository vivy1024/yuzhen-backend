<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    /**
     * 表名
     */
    protected $table = 'feedbacks';

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'user_id',
        'type',
        'content',
        'images',
        'contact',
        'status',
        'reply',
        'reply_at',
        'reply_by',
    ];

    /**
     * 类型转换
     */
    protected $casts = [
        'images' => 'array',
        'reply_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 反馈类型
     */
    const TYPE_FEATURE = 'feature';
    const TYPE_BUG = 'bug';
    const TYPE_QUESTION = 'question';
    const TYPE_OTHER = 'other';

    /**
     * 反馈状态
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    /**
     * 获取类型标签
     */
    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_FEATURE => '功能建议',
            self::TYPE_BUG => 'Bug报告',
            self::TYPE_QUESTION => '使用问题',
            self::TYPE_OTHER => '其他',
        ];
    }

    /**
     * 获取状态标签
     */
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_RESOLVED => '已解决',
            self::STATUS_CLOSED => '已关闭',
        ];
    }

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联回复人
     */
    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reply_by');
    }

    /**
     * 获取类型标签属性
     */
    public function getTypeLabelAttribute(): string
    {
        return self::getTypeLabels()[$this->type] ?? $this->type;
    }

    /**
     * 获取状态标签属性
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }

    /**
     * 是否已回复
     */
    public function hasReply(): bool
    {
        return !empty($this->reply);
    }

    /**
     * 是否待处理
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * 作用域：按状态筛选
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 作用域：按类型筛选
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * 作用域：待处理
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
