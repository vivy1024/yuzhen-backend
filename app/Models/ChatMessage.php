<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChatMessage - AI聊天消息模型
 * 
 * 存储用户消息和AI回复
 */
class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic_id',
        'user_id',
        'role',
        'content',
        'metadata',
        'client_id',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * 关联话题
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ChatTopic::class, 'topic_id');
    }

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
