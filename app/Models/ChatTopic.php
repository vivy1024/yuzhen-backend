<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ChatTopic Model - AI聊天话题
 * 
 * 用于组织和管理用户的AI对话，将多轮对话归类到话题下
 * 
 * @property int $id
 * @property int $user_id 用户ID
 * @property string $name 话题名称
 * @property string|null $description 话题描述
 * @property int $message_count 消息数量
 * @property string|null $last_message 最后一条消息
 * @property \Carbon\Carbon|null $last_message_at 最后消息时间
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @version 1.0.0
 * @date 2025-01-02
 */
class ChatTopic extends Model
{
    use SoftDeletes;
    
    /**
     * 表名
     */
    protected $table = 'chat_topics';
    
    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'message_count',
        'last_message',
        'last_message_at',
    ];
    
    /**
     * 属性类型转换
     */
    protected $casts = [
        'message_count' => 'integer',
        'last_message_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    /**
     * 关联：所属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * 关联：话题下的对话消息
     */
    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class, 'topic_id');
    }
    
    /**
     * 更新最后一条消息
     */
    public function updateLastMessage(string $message): bool
    {
        return $this->update([
            'last_message' => $message,
            'last_message_at' => now(),
        ]);
    }
    
    /**
     * 增加消息计数
     */
    public function incrementMessageCount(): bool
    {
        return $this->increment('message_count');
    }
    
    /**
     * 减少消息计数
     */
    public function decrementMessageCount(): bool
    {
        if ($this->message_count > 0) {
            return $this->decrement('message_count');
        }
        return true;
    }
    
    /**
     * Scope: 按用户筛选
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Scope: 最近活跃的话题
     */
    public function scopeRecentlyActive($query, int $days = 7)
    {
        return $query->where('last_message_at', '>=', now()->subDays($days));
    }
    
    /**
     * Scope: 按消息数量排序
     */
    public function scopeOrderByMessageCount($query, string $direction = 'desc')
    {
        return $query->orderBy('message_count', $direction);
    }
}
