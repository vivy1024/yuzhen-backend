<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * 积分流水模型 - 积分体系
 * 
 * 记录每次AI对话的积分消耗详情
 * 积分计算公式：credits = ceil(tokens × multiplier / 1000)
 * - Agent模式：multiplier = 1.5
 * - DAG模式：multiplier = 1.0
 * - 最小消耗：1积分
 * 
 * @property int $id
 * @property int $user_id 用户ID
 * @property int $credits 消耗积分数（正数为消耗，负数为充值/分享获得）
 * @property int $tokens 消耗Token总数
 * @property string $mode 查询模式（dag/agent）
 * @property string|null $template_name DAG模板名称
 * @property string|null $conversation_id 会话ID
 * @property int $input_tokens 输入Token数
 * @property int $output_tokens 输出Token数
 * @property string|null $description 交易描述
 * @property Carbon $created_at 创建时间
 * 
 * @property-read User $user
 * 
 * @version v1.0.0
 * @date 2026-02-05
 * @author 薛小川
 * @requirements 3.1, 3.2, 3.3
 */
class CreditTransaction extends Model
{
    use HasFactory;

    /**
     * 表名
     */
    protected $table = 'credit_transactions';

    /**
     * 禁用updated_at（流水记录不可修改）
     */
    const UPDATED_AT = null;

    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'user_id',
        'credits',
        'tokens',
        'mode',
        'template_name',
        'conversation_id',
        'input_tokens',
        'output_tokens',
        'description',
    ];

    /**
     * 字段类型转换
     */
    protected $casts = [
        'user_id' => 'integer',
        'credits' => 'integer',
        'tokens' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * 查询模式常量
     */
    const MODE_DAG = 'dag';
    const MODE_AGENT = 'agent';

    /**
     * 模式倍率常量
     */
    const MODE_MULTIPLIERS = [
        self::MODE_DAG => 1.0,
        self::MODE_AGENT => 1.5,
    ];

    // ==================== 关联关系 ====================

    /**
     * 关联用户
     * 
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==================== Scope查询方法 ====================

    /**
     * 按用户ID查询
     * 
     * @param Builder $query
     * @param int $userId
     * @return Builder
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 按会话ID查询
     * 
     * @param Builder $query
     * @param string $conversationId
     * @return Builder
     */
    public function scopeForConversation(Builder $query, string $conversationId): Builder
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * 按查询模式筛选
     * 
     * @param Builder $query
     * @param string $mode dag|agent
     * @return Builder
     */
    public function scopeByMode(Builder $query, string $mode): Builder
    {
        return $query->where('mode', $mode);
    }

    /**
     * 按DAG模板名称筛选
     * 
     * @param Builder $query
     * @param string $templateName
     * @return Builder
     */
    public function scopeByTemplate(Builder $query, string $templateName): Builder
    {
        return $query->where('template_name', $templateName);
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
     * 只查询消耗记录（credits > 0）
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeConsumptions(Builder $query): Builder
    {
        return $query->where('credits', '>', 0);
    }

    /**
     * 只查询获得记录（credits < 0，如分享获得）
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeReceipts(Builder $query): Builder
    {
        return $query->where('credits', '<', 0);
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
     * 获取用户今日消耗总积分
     * 
     * @param int $userId
     * @return int
     */
    public static function getTodayConsumption(int $userId): int
    {
        return static::forUser($userId)
            ->today()
            ->consumptions()
            ->sum('credits');
    }

    /**
     * 获取用户本周消耗总积分
     * 
     * @param int $userId
     * @return int
     */
    public static function getWeeklyConsumption(int $userId): int
    {
        return static::forUser($userId)
            ->thisWeek()
            ->consumptions()
            ->sum('credits');
    }

    /**
     * 获取用户本月消耗总积分
     * 
     * @param int $userId
     * @return int
     */
    public static function getMonthlyConsumption(int $userId): int
    {
        return static::forUser($userId)
            ->thisMonth()
            ->consumptions()
            ->sum('credits');
    }

    /**
     * 获取用户消耗统计摘要
     * 
     * @param int $userId
     * @return array
     */
    public static function getConsumptionSummary(int $userId): array
    {
        return [
            'today' => static::getTodayConsumption($userId),
            'this_week' => static::getWeeklyConsumption($userId),
            'this_month' => static::getMonthlyConsumption($userId),
        ];
    }

    /**
     * 创建积分消耗记录
     * 
     * @param int $userId
     * @param int $credits
     * @param int $tokens
     * @param string $mode
     * @param array $extra 额外字段（template_name, conversation_id, input_tokens, output_tokens, description）
     * @return static
     */
    public static function createConsumption(
        int $userId,
        int $credits,
        int $tokens,
        string $mode,
        array $extra = []
    ): self {
        return static::create(array_merge([
            'user_id' => $userId,
            'credits' => $credits,
            'tokens' => $tokens,
            'mode' => $mode,
            'input_tokens' => $extra['input_tokens'] ?? 0,
            'output_tokens' => $extra['output_tokens'] ?? 0,
        ], array_intersect_key($extra, array_flip([
            'template_name',
            'conversation_id',
            'description',
        ]))));
    }

    // ==================== 实例方法 ====================

    /**
     * 检查是否为DAG模式
     * 
     * @return bool
     */
    public function isDagMode(): bool
    {
        return $this->mode === self::MODE_DAG;
    }

    /**
     * 检查是否为Agent模式
     * 
     * @return bool
     */
    public function isAgentMode(): bool
    {
        return $this->mode === self::MODE_AGENT;
    }

    /**
     * 获取模式的中文名称
     * 
     * @return string
     */
    public function getModeLabel(): string
    {
        return match ($this->mode) {
            self::MODE_DAG => 'DAG模式',
            self::MODE_AGENT => 'Agent模式',
            default => '未知模式',
        };
    }

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
}
