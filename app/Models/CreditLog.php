<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * 额度变更日志模型 - 会员自动化控制系统
 * 
 * 记录管理员为用户添加/扣减额度的操作日志
 * 
 * @property int $id
 * @property int $user_id 用户ID
 * @property int $dag_amount DAG额度变更量（正数增加，负数扣减）
 * @property int $agent_amount Agent额度变更量（正数增加，负数扣减）
 * @property string $reason 变更原因
 * @property int|null $admin_id 操作管理员ID
 * @property Carbon $created_at
 * 
 * @property-read User $user
 * @property-read User|null $admin
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 8.3
 */
class CreditLog extends Model
{
    use HasFactory;

    protected $table = 'credit_logs';

    /**
     * 禁用updated_at，只使用created_at
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'dag_amount',
        'agent_amount',
        'reason',
        'admin_id',
    ];

    protected $casts = [
        'dag_amount' => 'integer',
        'agent_amount' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * 关联用户（被操作的用户）
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联管理员（执行操作的管理员）
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * 创建额度变更日志
     * 
     * @param int $userId 用户ID
     * @param int $dagAmount DAG额度变更量
     * @param int $agentAmount Agent额度变更量
     * @param string $reason 变更原因
     * @param int|null $adminId 管理员ID
     * @return static
     */
    public static function createLog(
        int $userId,
        int $dagAmount,
        int $agentAmount,
        string $reason,
        ?int $adminId = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'dag_amount' => $dagAmount,
            'agent_amount' => $agentAmount,
            'reason' => $reason,
            'admin_id' => $adminId,
        ]);
    }

    /**
     * 获取用户的额度变更历史
     * 
     * @param int $userId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUserLogs(int $userId, int $limit = 50)
    {
        return static::where('user_id', $userId)
            ->with('admin:id,name,username')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 是否为增加操作
     * 
     * @return bool
     */
    public function isAddition(): bool
    {
        return $this->dag_amount > 0 || $this->agent_amount > 0;
    }

    /**
     * 是否为扣减操作
     * 
     * @return bool
     */
    public function isDeduction(): bool
    {
        return $this->dag_amount < 0 || $this->agent_amount < 0;
    }

    /**
     * 获取变更类型描述
     * 
     * @return string
     */
    public function getTypeDescription(): string
    {
        if ($this->isAddition()) {
            return '增加额度';
        } elseif ($this->isDeduction()) {
            return '扣减额度';
        }
        return '无变更';
    }
}
