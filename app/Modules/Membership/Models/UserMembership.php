<?php

namespace App\Modules\Membership\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\User\Models\User;
use App\Modules\Membership\Models\Order;

/**
 * User Membership Model
 * 
 * 用户会员关系模型
 */
class UserMembership extends Model
{
    use HasFactory;

    protected $table = 'user_memberships';

    protected $fillable = [
        'user_id',
        'membership_id',
        'started_at',
        'expires_at',
        'is_active',
        'auto_renew',
        'order_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联：会员等级
     */
    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * 关联：订单
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * 是否激活
     */
    public function isActive(): bool
    {
        return $this->is_active == 1 && $this->expires_at->isFuture();
    }

    /**
     * 是否已过期
     */
    public function isExpired(): bool
    {
        return $this->is_active == 0 || $this->expires_at->isPast();
    }

    /**
     * 剩余天数
     */
    public function getRemainingDaysAttribute(): int
    {
        if ($this->isExpired()) {
            return 0;
        }
        return now()->diffInDays($this->expires_at);
    }

    /**
     * 续费
     */
    public function renew(int $days): void
    {
        $this->expires_at = $this->expires_at->addDays($days);
        $this->is_active = 1;
        $this->save();
    }

    /**
     * 取消
     */
    public function cancel(): void
    {
        $this->is_active = 0;
        $this->auto_renew = false;
        $this->save();
    }

    /**
     * 作用域：激活的会员
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1)
                     ->where('expires_at', '>', now());
    }

    /**
     * 作用域：即将过期（7天内）
     */
    public function scopeExpiringSoon($query)
    {
        return $query->where('is_active', 1)
                     ->whereBetween('expires_at', [now(), now()->addDays(7)]);
    }
}

