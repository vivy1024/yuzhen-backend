<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * User Membership Model
 * 
 * 用户会员模型
 */
class UserMembership extends Model
{
    use HasFactory;

    protected $table = 'user_memberships';

    protected $fillable = [
        'user_id',
        'membership_type',
        'is_active',
        'started_at',
        'expires_at',
        'auto_renew',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_renew' => 'boolean',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 是否已过期
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * 剩余天数
     */
    public function getRemainingDaysAttribute(): int
    {
        if (!$this->expires_at) {
            return 0;
        }
        
        return max(0, now()->diffInDays($this->expires_at, false));
    }
}

