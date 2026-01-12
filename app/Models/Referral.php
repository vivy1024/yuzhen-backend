<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * 推荐关系模型 - 会员自动化控制系统
 * 
 * 记录用户推荐关系和奖励发放
 * 
 * @property int $id
 * @property int $referrer_id 推荐人ID
 * @property int $referee_id 被推荐人ID
 * @property string $status 状态：registered(已注册)/paid(已付费)
 * @property bool $reward_granted 奖励是否已发放
 * @property float $cashback_amount 返现金额
 * @property Carbon $created_at
 * 
 * @property-read User $referrer 推荐人
 * @property-read User $referee 被推荐人
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 15.4
 */
class Referral extends Model
{
    use HasFactory;

    protected $table = 'referrals';

    /**
     * 禁用updated_at，只使用created_at
     */
    const UPDATED_AT = null;

    /**
     * 状态常量
     */
    const STATUS_REGISTERED = 'registered';
    const STATUS_PAID = 'paid';

    /**
     * 奖励配置
     */
    const REWARD_DAYS = 3;  // 推荐奖励天数（暖心会员）
    const CASHBACK_RATE = 0.10;  // 返现比例（10%）
    const MAX_MONTHLY_CASHBACK = 50.00;  // 每月最大返现金额

    protected $fillable = [
        'referrer_id',
        'referee_id',
        'status',
        'reward_granted',
        'cashback_amount',
    ];

    protected $casts = [
        'reward_granted' => 'boolean',
        'cashback_amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    /**
     * 关联推荐人
     */
    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * 关联被推荐人
     */
    public function referee()
    {
        return $this->belongsTo(User::class, 'referee_id');
    }

    /**
     * 创建推荐关系
     * 
     * @param int $referrerId 推荐人ID
     * @param int $refereeId 被推荐人ID
     * @return static
     */
    public static function createReferral(int $referrerId, int $refereeId): self
    {
        return static::create([
            'referrer_id' => $referrerId,
            'referee_id' => $refereeId,
            'status' => self::STATUS_REGISTERED,
            'reward_granted' => false,
            'cashback_amount' => 0,
        ]);
    }

    /**
     * 根据被推荐人ID查找推荐关系
     * 
     * @param int $refereeId
     * @return static|null
     */
    public static function findByRefereeId(int $refereeId): ?self
    {
        return static::where('referee_id', $refereeId)->first();
    }

    /**
     * 获取推荐人的推荐统计
     * 
     * @param int $referrerId
     * @return array
     */
    public static function getReferrerStats(int $referrerId): array
    {
        $referrals = static::where('referrer_id', $referrerId)->get();
        
        return [
            'total_referrals' => $referrals->count(),
            'registered_count' => $referrals->where('status', self::STATUS_REGISTERED)->count(),
            'paid_count' => $referrals->where('status', self::STATUS_PAID)->count(),
            'rewards_granted' => $referrals->where('reward_granted', true)->count(),
            'total_cashback' => $referrals->sum('cashback_amount'),
        ];
    }

    /**
     * 获取推荐人本月已获得的返现金额
     * 
     * @param int $referrerId
     * @return float
     */
    public static function getMonthlyTotalCashback(int $referrerId): float
    {
        return static::where('referrer_id', $referrerId)
            ->where('status', self::STATUS_PAID)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('cashback_amount');
    }

    /**
     * 计算返现金额（考虑月度上限）
     * 
     * @param int $referrerId
     * @param float $orderAmount
     * @return float
     */
    public static function calculateCashback(int $referrerId, float $orderAmount): float
    {
        $potentialCashback = $orderAmount * self::CASHBACK_RATE;
        $monthlyTotal = self::getMonthlyTotalCashback($referrerId);
        $remaining = self::MAX_MONTHLY_CASHBACK - $monthlyTotal;
        
        return min($potentialCashback, max(0, $remaining));
    }

    /**
     * 标记奖励已发放
     * 
     * @return bool
     */
    public function markRewardGranted(): bool
    {
        return $this->update(['reward_granted' => true]);
    }

    /**
     * 更新为已付费状态并记录返现
     * 
     * @param float $cashbackAmount
     * @return bool
     */
    public function markAsPaid(float $cashbackAmount = 0): bool
    {
        return $this->update([
            'status' => self::STATUS_PAID,
            'cashback_amount' => $cashbackAmount,
        ]);
    }

    /**
     * 是否已注册状态
     * 
     * @return bool
     */
    public function isRegistered(): bool
    {
        return $this->status === self::STATUS_REGISTERED;
    }

    /**
     * 是否已付费状态
     * 
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * 获取状态描述
     * 
     * @return string
     */
    public function getStatusDescription(): string
    {
        return match($this->status) {
            self::STATUS_REGISTERED => '已注册',
            self::STATUS_PAID => '已付费',
            default => '未知状态',
        };
    }
}
