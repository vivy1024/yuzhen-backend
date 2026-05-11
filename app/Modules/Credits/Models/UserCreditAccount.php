<?php

namespace App\Modules\Credits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\User\Models\User;

/**
 * UserCreditAccount - 用户积分账户模型
 *
 * 对应 user_credit_accounts 表
 *
 * @property int $id
 * @property int $user_id
 * @property string $balance 当前可用余额
 * @property string $monthly_limit 月度配额
 * @property string $used_this_month 本月已用
 * @property string $bonus_balance 奖励余额（不重置）
 * @property string $tier free|warmheart|energy
 * @property \Carbon\Carbon|null $last_reset_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class UserCreditAccount extends Model
{
    protected $table = 'user_credit_accounts';

    protected $fillable = [
        'user_id',
        'balance',
        'monthly_limit',
        'used_this_month',
        'bonus_balance',
        'tier',
        'last_reset_at',
    ];

    protected $casts = [
        'balance' => 'decimal:6',
        'monthly_limit' => 'decimal:6',
        'used_this_month' => 'decimal:6',
        'bonus_balance' => 'decimal:6',
        'last_reset_at' => 'datetime',
    ];

    /**
     * 所属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取总可用余额（balance + bonus_balance）
     */
    public function getTotalAvailableAttribute(): string
    {
        return bcadd($this->balance, $this->bonus_balance, 6);
    }

    /**
     * 本月剩余配额
     */
    public function getMonthlyRemainingAttribute(): string
    {
        return bcsub($this->monthly_limit, $this->used_this_month, 6);
    }
}
