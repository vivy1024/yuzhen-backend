<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * 用户积分模型 - 积分体系
 * 
 * 存储用户的每日积分配额和消耗情况
 * 积分计算公式：credits = ceil(tokens × multiplier / 1000)
 * - Agent模式：multiplier = 1.5
 * - DAG模式：multiplier = 1.0
 * 
 * @property int $id
 * @property int $user_id 用户ID
 * @property int $daily_quota 每日积分配额（免费10/暖心50/能量200）
 * @property int $daily_consumed 今日已消耗积分
 * @property int $total_consumed 历史总消耗积分
 * @property Carbon $last_reset_date 上次配额重置日期
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property-read int $remaining 剩余积分（计算属性）
 * @property-read User $user
 * 
 * @version v2.0.0
 * @date 2026-02-05
 * @author 薛小川
 * @requirements 4.4, 2.4
 */
class UserCredit extends Model
{
    use HasFactory;

    /**
     * 表名
     */
    protected $table = 'user_credits';

    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'user_id',
        'daily_quota',
        'daily_consumed',
        'total_consumed',
        'last_reset_date',
    ];

    /**
     * 字段类型转换
     */
    protected $casts = [
        'user_id' => 'integer',
        'daily_quota' => 'integer',
        'daily_consumed' => 'integer',
        'total_consumed' => 'integer',
        'last_reset_date' => 'date',
    ];

    /**
     * 每日配额常量（按会员等级）
     */
    const DAILY_QUOTAS = [
        'free' => 10,
        'warmheart' => 50,
        'energy' => 200,
    ];

    /**
     * 关联用户
     * 
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取剩余积分（属性访问器）
     * 
     * 计算公式：remaining = max(0, daily_quota - daily_consumed)
     * 确保返回值非负
     * 
     * @return int
     * @requirements 4.4
     */
    public function getRemainingAttribute(): int
    {
        return max(0, $this->daily_quota - $this->daily_consumed);
    }

    /**
     * 检查是否需要重置每日配额
     * 
     * 当last_reset_date早于今天时，需要重置
     * 配额在北京时间00:00重置
     * 
     * @return bool
     * @requirements 2.4
     */
    public function needsReset(): bool
    {
        // 如果last_reset_date为null，需要重置
        if ($this->last_reset_date === null) {
            return true;
        }
        
        // 比较last_reset_date是否早于今天
        return $this->last_reset_date->lt(today());
    }

    /**
     * 获取用户积分记录（静态方法）
     * 
     * @param int $userId
     * @return static|null
     */
    public static function getByUserId(int $userId): ?self
    {
        return static::where('user_id', $userId)->first();
    }

    /**
     * 获取或创建用户积分记录
     * 
     * @param int $userId
     * @param string $membershipTier 会员等级（free/warmheart/energy）
     * @return static
     */
    public static function getOrCreate(int $userId, string $membershipTier = 'free'): self
    {
        $quota = self::DAILY_QUOTAS[$membershipTier] ?? self::DAILY_QUOTAS['free'];
        
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'daily_quota' => $quota,
                'daily_consumed' => 0,
                'total_consumed' => 0,
                'last_reset_date' => today(),
            ]
        );
    }

    /**
     * 检查是否有足够的积分
     * 
     * @param int $credits 需要的积分数
     * @return bool
     */
    public function hasSufficientCredits(int $credits): bool
    {
        return $this->remaining >= $credits;
    }

    /**
     * 消耗积分
     * 
     * @param int $credits 消耗的积分数
     * @return bool 是否成功
     */
    public function consumeCredits(int $credits): bool
    {
        if (!$this->hasSufficientCredits($credits)) {
            return false;
        }
        
        $this->daily_consumed += $credits;
        $this->total_consumed += $credits;
        $this->save();
        
        return true;
    }

    /**
     * 重置每日配额
     * 
     * @param int|null $newQuota 新的配额（如果为null则保持原配额）
     * @return void
     */
    public function resetDailyQuota(?int $newQuota = null): void
    {
        $this->daily_consumed = 0;
        $this->last_reset_date = today();
        
        if ($newQuota !== null) {
            $this->daily_quota = $newQuota;
        }
        
        $this->save();
    }

    /**
     * 更新会员等级对应的配额
     * 
     * @param string $membershipTier 会员等级
     * @return void
     */
    public function updateQuotaForTier(string $membershipTier): void
    {
        $quota = self::DAILY_QUOTAS[$membershipTier] ?? self::DAILY_QUOTAS['free'];
        $this->daily_quota = $quota;
        $this->save();
    }

    /**
     * 检查是否为低余额状态（剩余积分低于20%）
     * 
     * @return bool
     * @requirements 4.5
     */
    public function isLowBalance(): bool
    {
        if ($this->daily_quota <= 0) {
            return false;
        }
        
        return ($this->remaining / $this->daily_quota) < 0.2;
    }

    /**
     * 获取使用百分比
     * 
     * @return float 0-100的百分比
     */
    public function getUsagePercentage(): float
    {
        if ($this->daily_quota <= 0) {
            return 0;
        }
        
        return min(100, ($this->daily_consumed / $this->daily_quota) * 100);
    }
}
