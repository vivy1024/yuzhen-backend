<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * 用户额度模型 - 会员自动化控制系统
 * 
 * 存储用户的额外DAG和Agent次数（打赏奖励）
 * 
 * @property int $id
 * @property int $user_id 用户ID
 * @property int $dag_credits DAG额外次数
 * @property int $agent_credits Agent额外次数
 * @property Carbon $updated_at
 * 
 * @property-read User $user
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 8.2
 */
class UserCredit extends Model
{
    use HasFactory;

    protected $table = 'user_credits';

    /**
     * 禁用created_at，只使用updated_at
     */
    const CREATED_AT = null;

    protected $fillable = [
        'user_id',
        'dag_credits',
        'agent_credits',
    ];

    protected $casts = [
        'dag_credits' => 'integer',
        'agent_credits' => 'integer',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取用户额度记录
     * 
     * @param int $userId
     * @return static|null
     */
    public static function getByUserId(int $userId): ?self
    {
        return static::where('user_id', $userId)->first();
    }

    /**
     * 获取或创建用户额度记录
     * 
     * @param int $userId
     * @return static
     */
    public static function getOrCreate(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'dag_credits' => 0,
                'agent_credits' => 0,
            ]
        );
    }

    /**
     * 添加DAG额度
     * 
     * @param int $amount 添加数量（必须为正数）
     * @return int 添加后的总额度
     * @throws \InvalidArgumentException
     */
    public function addDagCredits(int $amount): int
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('添加额度必须为正数');
        }
        
        $this->increment('dag_credits', $amount);
        return $this->dag_credits;
    }

    /**
     * 添加Agent额度
     * 
     * @param int $amount 添加数量（必须为正数）
     * @return int 添加后的总额度
     * @throws \InvalidArgumentException
     */
    public function addAgentCredits(int $amount): int
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('添加额度必须为正数');
        }
        
        $this->increment('agent_credits', $amount);
        return $this->agent_credits;
    }

    /**
     * 扣减DAG额度
     * 
     * @param int $amount 扣减数量（必须为正数）
     * @return bool 是否扣减成功
     */
    public function deductDagCredits(int $amount = 1): bool
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('扣减额度必须为正数');
        }
        
        if ($this->dag_credits < $amount) {
            return false;
        }
        
        $this->decrement('dag_credits', $amount);
        return true;
    }

    /**
     * 扣减Agent额度
     * 
     * @param int $amount 扣减数量（必须为正数）
     * @return bool 是否扣减成功
     */
    public function deductAgentCredits(int $amount = 1): bool
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('扣减额度必须为正数');
        }
        
        if ($this->agent_credits < $amount) {
            return false;
        }
        
        $this->decrement('agent_credits', $amount);
        return true;
    }

    /**
     * 检查是否有足够的DAG额度
     * 
     * @param int $amount
     * @return bool
     */
    public function hasDagCredits(int $amount = 1): bool
    {
        return $this->dag_credits >= $amount;
    }

    /**
     * 检查是否有足够的Agent额度
     * 
     * @param int $amount
     * @return bool
     */
    public function hasAgentCredits(int $amount = 1): bool
    {
        return $this->agent_credits >= $amount;
    }

    /**
     * 获取总额度
     * 
     * @return int
     */
    public function getTotalCredits(): int
    {
        return $this->dag_credits + $this->agent_credits;
    }
}
