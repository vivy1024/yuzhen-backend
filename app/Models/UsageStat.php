<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * 用量统计模型 - 会员自动化控制系统
 * 
 * 记录用户每日AI查询次数（DAG模式和Agent模式分开统计）
 * 
 * @property int $id
 * @property int $user_id 用户ID
 * @property Carbon $date 统计日期
 * @property int $dag_queries DAG模式查询次数
 * @property int $agent_queries Agent模式查询次数
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property-read User $user
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 8.1
 */
class UsageStat extends Model
{
    use HasFactory;

    protected $table = 'usage_stats';

    protected $fillable = [
        'user_id',
        'date',
        'dag_queries',
        'agent_queries',
    ];

    protected $casts = [
        'date' => 'date',
        'dag_queries' => 'integer',
        'agent_queries' => 'integer',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取用户今日用量记录
     * 
     * @param int $userId
     * @return static|null
     */
    public static function getTodayUsage(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->where('date', Carbon::today())
            ->first();
    }

    /**
     * 获取或创建用户今日用量记录
     * 
     * @param int $userId
     * @return static
     */
    public static function getOrCreateTodayUsage(int $userId): self
    {
        return static::firstOrCreate(
            [
                'user_id' => $userId,
                'date' => Carbon::today(),
            ],
            [
                'dag_queries' => 0,
                'agent_queries' => 0,
            ]
        );
    }

    /**
     * 增加DAG查询次数
     * 
     * @return int 增加后的次数
     */
    public function incrementDagQueries(): int
    {
        $this->increment('dag_queries');
        return $this->dag_queries;
    }

    /**
     * 增加Agent查询次数
     * 
     * @return int 增加后的次数
     */
    public function incrementAgentQueries(): int
    {
        $this->increment('agent_queries');
        return $this->agent_queries;
    }

    /**
     * 获取总查询次数
     * 
     * @return int
     */
    public function getTotalQueries(): int
    {
        return $this->dag_queries + $this->agent_queries;
    }
}
