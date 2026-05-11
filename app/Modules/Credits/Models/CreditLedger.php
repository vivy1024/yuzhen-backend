<?php

namespace App\Modules\Credits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\User\Models\User;

/**
 * CreditLedger - 积分流水模型
 *
 * 对应 credit_ledger 表
 *
 * @property int $id
 * @property int $user_id
 * @property string $type earn|spend|reset|bonus|admin_adjust
 * @property string $amount 变动量（正=获得，负=消耗）
 * @property string $balance_after 变动后余额
 * @property string|null $model 使用的模型（spend 时）
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property string|null $session_id
 * @property string|null $source 来源：register/checkin/invite/admin/ai_chat
 * @property string|null $description
 * @property string|null $idempotency_key
 * @property \Carbon\Carbon $created_at
 */
class CreditLedger extends Model
{
    protected $table = 'credit_ledger';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_after',
        'model',
        'input_tokens',
        'output_tokens',
        'session_id',
        'source',
        'description',
        'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'decimal:6',
        'balance_after' => 'decimal:6',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'created_at' => 'datetime',
    ];

    // 类型常量
    const TYPE_EARN = 'earn';
    const TYPE_SPEND = 'spend';
    const TYPE_RESET = 'reset';
    const TYPE_BONUS = 'bonus';
    const TYPE_ADMIN_ADJUST = 'admin_adjust';

    // 来源常量
    const SOURCE_REGISTER = 'register';
    const SOURCE_CHECKIN = 'checkin';
    const SOURCE_INVITE = 'invite';
    const SOURCE_ADMIN = 'admin';
    const SOURCE_AI_CHAT = 'ai_chat';

    /**
     * 所属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: 按用户筛选
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: 按类型筛选
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: 消费记录
     */
    public function scopeSpending($query)
    {
        return $query->where('type', self::TYPE_SPEND);
    }

    /**
     * Scope: 获得记录
     */
    public function scopeEarning($query)
    {
        return $query->where('type', self::TYPE_EARN);
    }

    /**
     * 获取类型标签
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_EARN => '获得',
            self::TYPE_SPEND => '消耗',
            self::TYPE_RESET => '重置',
            self::TYPE_BONUS => '奖励',
            self::TYPE_ADMIN_ADJUST => '管理员调整',
            default => '未知',
        };
    }
}
