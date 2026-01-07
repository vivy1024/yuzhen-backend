<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * 用户会员关联模型
 * 
 * @property int $id
 * @property int $user_id 用户ID
 * @property int $membership_id 会员等级ID
 * @property string $order_id 订单ID
 * @property Carbon $started_at 生效时间
 * @property Carbon $expires_at 过期时间
 * @property bool $is_active 是否激活
 */
class UserMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'membership_id',
        'order_id',
        'started_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联会员等级
     */
    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * 是否已过期
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false; // 永久会员
        }
        
        return Carbon::now()->greaterThan($this->expires_at);
    }

    /**
     * 是否有效（激活且未过期）
     */
    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * 剩余天数
     */
    public function remainingDays(): ?int
    {
        if (!$this->expires_at) {
            return null; // 永久会员
        }
        
        $days = Carbon::now()->diffInDays($this->expires_at, false);
        return max(0, (int)$days);
    }
}

































