<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 用户投诉模型
 * 
 * 用于存储和管理用户投诉信息
 * Requirements: 16.5
 * 
 * @property int $id
 * @property int $user_id
 * @property int|null $chat_session_id
 * @property string $type
 * @property string $content
 * @property string|null $screenshot_url
 * @property string $status
 * @property string|null $handler_id
 * @property string|null $handler_response
 * @property \Carbon\Carbon|null $handled_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Complaint extends Model
{
    use HasFactory;

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'user_id',
        'chat_session_id',
        'type',
        'content',
        'screenshot_url',
        'status',
        'handler_id',
        'handler_response',
        'handled_at',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'handled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 投诉类型常量
     */
    const TYPE_CONTENT_QUALITY = 'content_quality';      // 内容质量问题
    const TYPE_CONTENT_SAFETY = 'content_safety';        // 内容安全问题
    const TYPE_TECHNICAL_ERROR = 'technical_error';      // 技术错误
    const TYPE_INAPPROPRIATE = 'inappropriate';          // 不当内容
    const TYPE_OTHER = 'other';                          // 其他问题

    /**
     * 投诉状态常量
     */
    const STATUS_PENDING = 'pending';       // 待处理
    const STATUS_PROCESSING = 'processing'; // 处理中
    const STATUS_RESOLVED = 'resolved';     // 已解决
    const STATUS_REJECTED = 'rejected';     // 已驳回
    const STATUS_CLOSED = 'closed';         // 已关闭

    /**
     * 获取所有投诉类型
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_CONTENT_QUALITY => '内容质量问题',
            self::TYPE_CONTENT_SAFETY => '内容安全问题',
            self::TYPE_TECHNICAL_ERROR => '技术错误',
            self::TYPE_INAPPROPRIATE => '不当内容',
            self::TYPE_OTHER => '其他问题',
        ];
    }

    /**
     * 获取所有状态
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_RESOLVED => '已解决',
            self::STATUS_REJECTED => '已驳回',
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
     * 关联聊天会话
     */
    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    /**
     * 获取类型标签
     */
    public function getTypeLabelAttribute(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    /**
     * 获取状态标签
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /**
     * 是否已处理
     */
    public function isHandled(): bool
    {
        return in_array($this->status, [
            self::STATUS_RESOLVED,
            self::STATUS_REJECTED,
            self::STATUS_CLOSED,
        ]);
    }

    /**
     * 标记为处理中
     */
    public function markAsProcessing(string $handlerId): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'handler_id' => $handlerId,
        ]);
    }

    /**
     * 标记为已解决
     */
    public function markAsResolved(string $response): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'handler_response' => $response,
            'handled_at' => now(),
        ]);
    }

    /**
     * 标记为已驳回
     */
    public function markAsRejected(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'handler_response' => $reason,
            'handled_at' => now(),
        ]);
    }

    /**
     * 作用域：待处理
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * 作用域：处理中
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * 作用域：已处理
     */
    public function scopeHandled($query)
    {
        return $query->whereIn('status', [
            self::STATUS_RESOLVED,
            self::STATUS_REJECTED,
            self::STATUS_CLOSED,
        ]);
    }
}
