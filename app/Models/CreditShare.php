<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * 积分分享模型 - 积分体系（阶段2预留）
 * 
 * 记录能量会员分享积分给好友的记录
 * 
 * 分享规则：
 * - 只有能量会员可以分享积分
 * - 每日分享给同一用户上限50积分
 * - 分享会同时创建双方的流水记录
 * 
 * @property int $id
 * @property int $sender_id 发送者用户ID（能量会员）
 * @property int $receiver_id 接收者用户ID
 * @property int $credits 分享的积分数量
 * @property string|null $message 分享留言
 * @property Carbon $created_at 创建时间
 * 
 * @property-read User $sender
 * @property-read User $receiver
 * 
 * @version v1.0.0
 * @date 2026-02-05
 * @author 薛小川
 * @requirements 8.3
 */
class CreditShare extends Model
{
    use HasFactory;

    /**
     * 表名
     */
    protected $table = 'credit_shares';

    /**
     * 禁用updated_at（分享记录不可修改）
     */
    const UPDATED_AT = null;

    /**
     * 每日分享给同一用户的上限
     */
    const DAILY_SHARE_LIMIT_PER_RECIPIENT = 50;

    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'credits',
        'message',
    ];

    /**
     * 字段类型转换
     */
    protected $casts = [
        'sender_id' => 'integer',
        'receiver_id' => 'integer',
        'credits' => 'integer',
        'created_at' => 'datetime',
    ];

    // ==================== 关联关系 ====================

    /**
     * 关联发送者（分享积分的用户）
     * 
     * @return BelongsTo
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * 关联接收者（获得积分的用户）
     * 
     * @return BelongsTo
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // ==================== Scope查询方法 ====================

    /**
     * 按发送者ID查询
     * 
     * @param Builder $query
     * @param int $senderId
     * @return Builder
     */
    public function scopeFromSender(Builder $query, int $senderId): Builder
    {
        return $query->where('sender_id', $senderId);
    }

    /**
     * 按接收者ID查询
     * 
     * @param Builder $query
     * @param int $receiverId
     * @return Builder
     */
    public function scopeToReceiver(Builder $query, int $receiverId): Builder
    {
        return $query->where('receiver_id', $receiverId);
    }

    /**
     * 查询特定发送者和接收者之间的分享记录
     * 
     * @param Builder $query
     * @param int $senderId
     * @param int $receiverId
     * @return Builder
     */
    public function scopeBetweenUsers(Builder $query, int $senderId, int $receiverId): Builder
    {
        return $query->where('sender_id', $senderId)
                     ->where('receiver_id', $receiverId);
    }

    /**
     * 按时间范围查询
     * 
     * @param Builder $query
     * @param Carbon|string $startDate
     * @param Carbon|string|null $endDate
     * @return Builder
     */
    public function scopeBetweenDates(Builder $query, $startDate, $endDate = null): Builder
    {
        $query->where('created_at', '>=', $startDate);
        
        if ($endDate !== null) {
            $query->where('created_at', '<=', $endDate);
        }
        
        return $query;
    }

    /**
     * 查询今日记录
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * 查询本周记录
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * 查询本月记录
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ]);
    }

    /**
     * 按创建时间降序排列（最新的在前）
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    // ==================== 静态方法 ====================

    /**
     * 获取今日发送者分享给特定接收者的积分总数
     * 
     * @param int $senderId
     * @param int $receiverId
     * @return int
     */
    public static function getTodayShareToRecipient(int $senderId, int $receiverId): int
    {
        return static::betweenUsers($senderId, $receiverId)
            ->today()
            ->sum('credits');
    }

    /**
     * 检查是否可以分享指定积分给接收者（每日限制检查）
     * 
     * @param int $senderId
     * @param int $receiverId
     * @param int $credits
     * @return bool
     */
    public static function canShareToRecipient(int $senderId, int $receiverId, int $credits): bool
    {
        $todayShared = static::getTodayShareToRecipient($senderId, $receiverId);
        return ($todayShared + $credits) <= self::DAILY_SHARE_LIMIT_PER_RECIPIENT;
    }

    /**
     * 获取今日还可以分享给特定接收者的积分数量
     * 
     * @param int $senderId
     * @param int $receiverId
     * @return int
     */
    public static function getRemainingShareQuota(int $senderId, int $receiverId): int
    {
        $todayShared = static::getTodayShareToRecipient($senderId, $receiverId);
        return max(0, self::DAILY_SHARE_LIMIT_PER_RECIPIENT - $todayShared);
    }

    /**
     * 获取用户今日分享出去的积分总数
     * 
     * @param int $senderId
     * @return int
     */
    public static function getTodaySentTotal(int $senderId): int
    {
        return static::fromSender($senderId)
            ->today()
            ->sum('credits');
    }

    /**
     * 获取用户今日收到的积分总数
     * 
     * @param int $receiverId
     * @return int
     */
    public static function getTodayReceivedTotal(int $receiverId): int
    {
        return static::toReceiver($receiverId)
            ->today()
            ->sum('credits');
    }

    /**
     * 创建积分分享记录
     * 
     * @param int $senderId 发送者ID
     * @param int $receiverId 接收者ID
     * @param int $credits 分享积分数量
     * @param string|null $message 分享留言
     * @return static
     */
    public static function createShare(
        int $senderId,
        int $receiverId,
        int $credits,
        ?string $message = null
    ): self {
        return static::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'credits' => $credits,
            'message' => $message,
        ]);
    }

    /**
     * 获取用户分享统计摘要
     * 
     * @param int $userId
     * @return array
     */
    public static function getShareSummary(int $userId): array
    {
        return [
            'sent' => [
                'today' => static::getTodaySentTotal($userId),
                'this_week' => static::fromSender($userId)->thisWeek()->sum('credits'),
                'this_month' => static::fromSender($userId)->thisMonth()->sum('credits'),
            ],
            'received' => [
                'today' => static::getTodayReceivedTotal($userId),
                'this_week' => static::toReceiver($userId)->thisWeek()->sum('credits'),
                'this_month' => static::toReceiver($userId)->thisMonth()->sum('credits'),
            ],
        ];
    }

    // ==================== 实例方法 ====================

    /**
     * 获取格式化的创建时间
     * 
     * @param string $format
     * @return string
     */
    public function getFormattedCreatedAt(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->created_at->format($format);
    }

    /**
     * 获取分享描述（用于流水记录）
     * 
     * @return string
     */
    public function getShareDescription(): string
    {
        return $this->message ?? '积分分享';
    }
}
