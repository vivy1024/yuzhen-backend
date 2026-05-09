<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * 用户会员关联模型 - 会员自动化控制系统
 * 
 * @property int $id
 * @property int $user_id 用户ID
 * @property int $membership_id 会员等级ID
 * @property string $order_id 订单ID
 * @property Carbon $started_at 生效时间
 * @property Carbon $expires_at 过期时间
 * @property bool $auto_renew 是否自动续费
 * @property string $status 状态：active/expired/cancelled
 * @property bool $is_active 是否激活
 * 
 * @property-read User $user
 * @property-read Membership $membership
 * 
 * @version v1.1.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 11.1
 */
class UserMembership extends Model
{
    use HasFactory;

    /**
     * 状态常量
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'membership_id',
        'order_id',
        'started_at',
        'expires_at',
        'auto_renew',
        'status',
        'is_active',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'auto_renew' => 'boolean',
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

    /**
     * 获取用户当前有效的会员记录
     * 
     * @param int $userId
     * @return static|null
     */
    public static function getActiveByUserId(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->where('status', self::STATUS_ACTIVE)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->with('membership')
            ->first();
    }

    /**
     * 获取即将到期的会员记录（用于提醒）
     * 
     * @param int $daysBeforeExpiry 到期前天数
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getExpiringMemberships(int $daysBeforeExpiry = 7)
    {
        $targetDate = Carbon::now()->addDays($daysBeforeExpiry)->toDateString();
        
        return static::where('status', self::STATUS_ACTIVE)
            ->where('is_active', true)
            ->whereDate('expires_at', $targetDate)
            ->with(['user', 'membership'])
            ->get();
    }

    /**
     * 获取已过期但未处理的会员记录
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getExpiredMemberships()
    {
        return static::where('status', self::STATUS_ACTIVE)
            ->where('is_active', true)
            ->where('expires_at', '<', Carbon::now())
            ->with(['user', 'membership'])
            ->get();
    }

    /**
     * 获取需要自动续费的会员记录
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAutoRenewMemberships()
    {
        return static::where('status', self::STATUS_ACTIVE)
            ->where('is_active', true)
            ->where('auto_renew', true)
            ->where('expires_at', '<=', Carbon::now()->addDay())
            ->with(['user', 'membership'])
            ->get();
    }

    /**
     * 标记为已过期
     * 
     * @return bool
     */
    public function markAsExpired(): bool
    {
        return $this->update([
            'status' => self::STATUS_EXPIRED,
            'is_active' => false,
        ]);
    }

    /**
     * 标记为已取消
     * 
     * @return bool
     */
    public function markAsCancelled(): bool
    {
        return $this->update([
            'status' => self::STATUS_CANCELLED,
            'is_active' => false,
        ]);
    }

    /**
     * 延长会员有效期
     * 
     * @param int $days 延长天数
     * @return bool
     */
    public function extend(int $days): bool
    {
        $newExpiresAt = $this->expires_at 
            ? $this->expires_at->addDays($days)
            : Carbon::now()->addDays($days);
        
        return $this->update([
            'expires_at' => $newExpiresAt,
            'status' => self::STATUS_ACTIVE,
            'is_active' => true,
        ]);
    }

    /**
     * 开启自动续费
     * 
     * @return bool
     */
    public function enableAutoRenew(): bool
    {
        return $this->update(['auto_renew' => true]);
    }

    /**
     * 关闭自动续费
     * 
     * @return bool
     */
    public function disableAutoRenew(): bool
    {
        return $this->update(['auto_renew' => false]);
    }
}



































