<?php

namespace App\Services;

use App\Models\UserCredit;
use App\Models\CreditLog;
use App\Models\CreditTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CreditService - 积分管理服务
 * 
 * 负责积分体系的核心功能：
 * - 积分计算（基于Token消耗）
 * - 积分余额查询
 * - 积分流水记录
 * - 积分扣除
 * - 每日配额重置
 * 
 * 积分计算公式：credits = ceil(tokens × multiplier / 1000)
 * - Agent模式：multiplier = 1.5
 * - DAG模式：multiplier = 1.0
 * - 最小消耗：1积分
 * 
 * 不变量：
 * - 积分余额永远不能为负数
 * - 所有积分变更必须记录流水
 * - 最小消耗为1积分
 * 
 * @version v2.0.0
 * @date 2026-02-05
 * @author 薛小川
 * @requirements 1.1-1.5, 2.1-2.5, 3.1-3.5, 4.1-4.5, 5.1-5.5
 */
class CreditService
{
    /**
     * 积分计算常量
     */
    const TOKENS_PER_CREDIT = 1000;
    const AGENT_MULTIPLIER = 1.5;
    const DAG_MULTIPLIER = 1.0;
    const MIN_CREDITS = 1;
    
    /**
     * 每日配额（按会员等级）
     */
    const DAILY_QUOTAS = [
        'free' => 10,
        'warmheart' => 50,
        'energy' => 200,
    ];
    
    /**
     * 计算积分消耗
     * 
     * 公式：credits = ceil(tokens × multiplier / 1000)
     * - Agent模式使用1.5x倍率
     * - DAG模式使用1.0x倍率
     * - 最小消耗为1积分
     * 
     * @param int $tokens Token消耗数量
     * @param string $mode 查询模式：'agent' 或 'dag'
     * @return int 计算后的积分消耗
     * 
     * @requirements 1.1, 1.2, 1.3, 1.4, 1.5
     */
    public function calculateCredits(int $tokens, string $mode): int
    {
        // 确定倍率：agent模式1.5x，其他模式（dag）1.0x
        $multiplier = strtolower($mode) === 'agent' 
            ? self::AGENT_MULTIPLIER 
            : self::DAG_MULTIPLIER;
        
        // 计算积分：向上取整
        $credits = (int) ceil(($tokens * $multiplier) / self::TOKENS_PER_CREDIT);
        
        // 确保最小消耗为1积分
        return max(self::MIN_CREDITS, $credits);
    }
    /**
     * 获取用户积分余额
     * 
     * 返回用户当前的积分状态，包括：
     * - 每日配额和消耗情况
     * - 会员等级信息
     * - 低余额警告
     * 
     * @param int $userId 用户ID
     * @return array 积分余额信息
     * 
     * 返回格式：
     * [
     *     'daily_quota' => int,        // 每日积分配额
     *     'daily_consumed' => int,     // 今日已消耗积分
     *     'remaining' => int,          // 剩余积分
     *     'total_consumed' => int,     // 历史总消耗
     *     'membership_tier' => string, // 会员等级
     *     'is_mvp_phase' => bool,      // 是否为MVP阶段（已结束）
     *     'low_balance_warning' => bool, // 低余额警告
     *     'last_reset' => string,      // 上次重置日期
     * ]
     * 
     * @requirements 4.1, 4.2, 4.3, 4.4, 4.5
     */
    public function getBalance(int $userId): array
    {
        // 获取用户会员等级
        $membershipTier = $this->getUserMembershipTier($userId);
        
        // 获取或创建用户积分记录
        $userCredit = UserCredit::getOrCreate($userId, $membershipTier);
        
        // 检查是否需要重置每日配额
        if ($userCredit->needsReset()) {
            $this->resetDailyQuota($userId);
            $userCredit->refresh();
        }
        
        return [
            'daily_quota' => $userCredit->daily_quota,
            'daily_consumed' => $userCredit->daily_consumed,
            'remaining' => $userCredit->remaining,
            'total_consumed' => $userCredit->total_consumed,
            'membership_tier' => $membershipTier,
            'is_mvp_phase' => false, // MVP阶段已结束，直接扣除积分
            'low_balance_warning' => $userCredit->isLowBalance(),
            'last_reset' => $userCredit->last_reset_date->toDateString(),
        ];
    }
    
    /**
     * 获取用户会员等级
     * 
     * 从users表的membership_tier字段获取用户会员等级
     * 
     * @param int $userId 用户ID
     * @return string 会员等级（free/warmheart/energy）
     */
    protected function getUserMembershipTier(int $userId): string
    {
        $user = \App\Modules\User\Models\User::find($userId);
        
        if (!$user) {
            return 'free';
        }
        
        // 获取用户的会员等级，默认为free
        $tier = $user->membership_tier ?? 'free';
        
        // 标准化tier值（newbie → free）
        if ($tier === 'newbie' || empty($tier)) {
            return 'free';
        }
        
        // 确保返回有效的等级
        if (!in_array($tier, ['free', 'warmheart', 'energy'])) {
            return 'free';
        }
        
        return $tier;
    }
    
    /**
     * 记录积分消耗
     * 
     * 记录完整的交易信息，同时扣除用户积分余额
     * 使用数据库事务确保原子性
     * 
     * @param int $userId 用户ID
     * @param array $data 交易数据
     *   - tokens: int 消耗的Token数
     *   - mode: string 查询模式（dag/agent）
     *   - template_name: string|null DAG模板名称
     *   - conversation_id: string|null 会话ID
     *   - input_tokens: int 输入Token数
     *   - output_tokens: int 输出Token数
     *   - description: string|null 描述
     * @return CreditTransaction
     * @throws \Exception 当积分不足或数据库操作失败时
     * 
     * @requirements 3.1, 3.2, 3.3, 5.1
     */
    public function recordTransaction(int $userId, array $data): CreditTransaction
    {
        // 验证必要参数
        if (!isset($data['tokens']) || !isset($data['mode'])) {
            throw new \InvalidArgumentException('tokens和mode参数是必需的');
        }
        
        // 计算积分消耗
        $credits = $this->calculateCredits($data['tokens'], $data['mode']);
        
        // 使用数据库事务确保原子性
        return DB::transaction(function () use ($userId, $credits, $data) {
            // 1. 获取用户会员等级
            $membershipTier = $this->getUserMembershipTier($userId);
            
            // 2. 获取或创建用户积分记录（加锁防止并发问题）
            $userCredit = UserCredit::where('user_id', $userId)->lockForUpdate()->first();
            
            if (!$userCredit) {
                $userCredit = UserCredit::getOrCreate($userId, $membershipTier);
                $userCredit = UserCredit::where('user_id', $userId)->lockForUpdate()->first();
            }
            
            // 3. 检查是否需要重置每日配额
            if ($userCredit->needsReset()) {
                $newQuota = self::DAILY_QUOTAS[$membershipTier] ?? self::DAILY_QUOTAS['free'];
                $userCredit->resetDailyQuota($newQuota);
            }
            
            // 4. 检查积分是否足够
            if (!$userCredit->hasSufficientCredits($credits)) {
                throw new \Exception("积分不足，当前剩余: {$userCredit->remaining}，需要: {$credits}");
            }
            
            // 5. 创建流水记录
            $transaction = CreditTransaction::create([
                'user_id' => $userId,
                'credits' => $credits,
                'tokens' => $data['tokens'],
                'mode' => $data['mode'],
                'template_name' => $data['template_name'] ?? null,
                'conversation_id' => $data['conversation_id'] ?? null,
                'input_tokens' => $data['input_tokens'] ?? 0,
                'output_tokens' => $data['output_tokens'] ?? 0,
                'description' => $data['description'] ?? null,
            ]);
            
            // 6. 扣除用户积分
            $userCredit->daily_consumed += $credits;
            $userCredit->total_consumed += $credits;
            $userCredit->save();
            
            // 7. 记录日志
            Log::info('积分消耗记录成功', [
                'user_id' => $userId,
                'credits' => $credits,
                'tokens' => $data['tokens'],
                'mode' => $data['mode'],
                'template_name' => $data['template_name'] ?? null,
                'remaining' => $userCredit->remaining,
            ]);
            
            return $transaction;
        });
    }
    
    /**
     * 检查用户积分是否足够
     * 
     * 检查用户剩余积分是否满足本次查询需求，
     * 如果不足则返回友好的提示消息和升级建议
     * 
     * @param int $userId 用户ID
     * @param int $requiredCredits 需要的积分数
     * @return array 检查结果
     * 
     * 返回格式：
     * [
     *     'sufficient' => bool,        // 积分是否充足
     *     'message' => string,         // 提示消息
     *     'remaining' => int,          // 剩余积分
     *     'required' => int,           // 需要的积分（仅当不足时）
     *     'membership_tier' => string, // 会员等级（仅当不足时）
     * ]
     * 
     * @requirements 5.2, 5.5
     */
    public function checkSufficientCredits(int $userId, int $requiredCredits): array
    {
        // 1. 获取用户会员等级
        $membershipTier = $this->getUserMembershipTier($userId);
        
        // 2. 获取或创建用户积分记录
        $userCredit = UserCredit::getOrCreate($userId, $membershipTier);
        
        // 3. 检查是否需要重置每日配额
        if ($userCredit->needsReset()) {
            $this->resetDailyQuota($userId);
            $userCredit->refresh();
        }
        
        // 4. 获取剩余积分
        $remaining = $userCredit->remaining;
        $sufficient = $remaining >= $requiredCredits;
        
        // 5. 积分充足，返回成功
        if ($sufficient) {
            return [
                'sufficient' => true,
                'message' => '积分充足',
                'remaining' => $remaining,
            ];
        }
        
        // 6. 积分不足，返回友好提示和升级建议
        $upgradeMessage = $this->getUpgradeMessage($membershipTier);
        
        return [
            'sufficient' => false,
            'message' => "今日积分已用完，剩余{$remaining}积分，需要{$requiredCredits}积分。{$upgradeMessage}",
            'remaining' => $remaining,
            'required' => $requiredCredits,
            'membership_tier' => $membershipTier,
        ];
    }
    
    /**
     * 获取升级提示消息
     * 
     * 根据用户当前会员等级，返回相应的升级建议
     * 
     * @param string $currentTier 当前会员等级
     * @return string 升级提示消息
     */
    protected function getUpgradeMessage(string $currentTier): string
    {
        return match ($currentTier) {
            'free' => '升级为暖心会员可获得每日50积分！',
            'warmheart' => '升级为能量会员可获得每日200积分！',
            'energy' => '明天将重置每日配额。',
            default => '升级会员可获得更多每日积分！',
        };
    }
    
    /**
     * 重置用户每日积分配额
     * 
     * 在北京时间00:00重置用户的每日配额
     * 根据用户当前会员等级设置新的配额
     * 
     * 重置逻辑：
     * 1. 获取用户当前会员等级
     * 2. 根据等级确定新的每日配额
     * 3. 重置daily_consumed为0
     * 4. 更新last_reset_date为今天
     * 5. 更新daily_quota为新配额
     * 
     * 配额标准：
     * - 免费用户(free): 10积分/天
     * - 暖心会员(warmheart): 50积分/天
     * - 能量会员(energy): 200积分/天
     * 
     * @param int $userId 用户ID
     * @return void
     * 
     * @requirements 2.1, 2.2, 2.3, 2.4
     */
    public function resetDailyQuota(int $userId): void
    {
        // 1. 获取用户当前会员等级
        $membershipTier = $this->getUserMembershipTier($userId);
        
        // 2. 根据会员等级确定新的每日配额
        $newQuota = self::DAILY_QUOTAS[$membershipTier] ?? self::DAILY_QUOTAS['free'];
        
        // 3. 获取或创建用户积分记录
        $userCredit = UserCredit::getOrCreate($userId, $membershipTier);
        
        // 4. 检查是否需要重置（避免重复重置）
        if (!$userCredit->needsReset()) {
            // 如果今天已经重置过，只更新配额（会员升级场景）
            if ($userCredit->daily_quota !== $newQuota) {
                $userCredit->daily_quota = $newQuota;
                $userCredit->save();
                
                Log::info('用户积分配额已更新（会员等级变更）', [
                    'user_id' => $userId,
                    'membership_tier' => $membershipTier,
                    'new_quota' => $newQuota,
                ]);
            }
            return;
        }
        
        // 5. 执行每日配额重置
        $oldConsumed = $userCredit->daily_consumed;
        $userCredit->resetDailyQuota($newQuota);
        
        // 6. 记录日志
        Log::info('用户每日积分配额已重置', [
            'user_id' => $userId,
            'membership_tier' => $membershipTier,
            'new_quota' => $newQuota,
            'previous_consumed' => $oldConsumed,
            'reset_date' => today()->toDateString(),
        ]);
    }
    
    /**
     * 获取用户额度（旧方法，保留兼容性）
     * 
     * @deprecated 使用 getBalance() 替代
     * @param int $userId 用户ID
     * @return array 额度信息
     */
    public function getCredits(int $userId): array
    {
        $balance = $this->getBalance($userId);
        
        return [
            'dag_credits' => $balance['remaining'],
            'agent_credits' => $balance['remaining'],
            'total_credits' => $balance['daily_quota'],
        ];
    }

    /**
     * 添加额度（管理员操作）
     * 
     * @param int $userId 用户ID
     * @param int $dagCredits DAG额度增加量
     * @param int $agentCredits Agent额度增加量
     * @param string $reason 添加原因
     * @param int|null $adminId 操作管理员ID
     * @return array 操作结果
     * 
     * 返回格式：
     * [
     *     'success' => bool,
     *     'dag_credits' => int,     // 添加后的DAG额度
     *     'agent_credits' => int,   // 添加后的Agent额度
     *     'message' => string,
     * ]
     */
    public function addCredits(
        int $userId,
        int $dagCredits,
        int $agentCredits,
        string $reason,
        ?int $adminId = null
    ): array {
        // 验证输入
        if ($dagCredits < 0 || $agentCredits < 0) {
            return [
                'success' => false,
                'dag_credits' => 0,
                'agent_credits' => 0,
                'message' => '添加额度必须为非负数',
            ];
        }
        
        if ($dagCredits === 0 && $agentCredits === 0) {
            return [
                'success' => false,
                'dag_credits' => 0,
                'agent_credits' => 0,
                'message' => '至少需要添加一种额度',
            ];
        }
        
        if (empty($reason)) {
            return [
                'success' => false,
                'dag_credits' => 0,
                'agent_credits' => 0,
                'message' => '必须提供添加原因',
            ];
        }
        
        try {
            DB::beginTransaction();
            
            // 获取或创建用户额度记录
            $credits = UserCredit::getOrCreate($userId);
            
            // 添加额度
            if ($dagCredits > 0) {
                $credits->addDagCredits($dagCredits);
            }
            
            if ($agentCredits > 0) {
                $credits->addAgentCredits($agentCredits);
            }
            
            // 刷新获取最新值
            $credits->refresh();
            
            // 记录日志
            CreditLog::createLog(
                $userId,
                $dagCredits,
                $agentCredits,
                $reason,
                $adminId
            );
            
            DB::commit();
            
            Log::info('额度添加成功', [
                'user_id' => $userId,
                'dag_credits' => $dagCredits,
                'agent_credits' => $agentCredits,
                'reason' => $reason,
                'admin_id' => $adminId,
                'new_dag_balance' => $credits->dag_credits,
                'new_agent_balance' => $credits->agent_credits,
            ]);
            
            return [
                'success' => true,
                'dag_credits' => $credits->dag_credits,
                'agent_credits' => $credits->agent_credits,
                'message' => "成功添加额度：DAG +{$dagCredits}，Agent +{$agentCredits}",
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('额度添加失败', [
                'user_id' => $userId,
                'dag_credits' => $dagCredits,
                'agent_credits' => $agentCredits,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'dag_credits' => 0,
                'agent_credits' => 0,
                'message' => '系统错误，请稍后重试',
            ];
        }
    }

    /**
     * 扣减额度（带事务和不变量检查）
     * 
     * 不变量：额度余额永远不能为负数
     * 
     * @param int $userId 用户ID
     * @param string $mode 扣减类型：'dag' 或 'agent'
     * @param int $amount 扣减数量（默认1）
     * @return array 操作结果
     * 
     * 返回格式：
     * [
     *     'success' => bool,
     *     'remaining' => int,       // 扣减后的剩余额度
     *     'message' => string,
     * ]
     */
    public function deductCredits(int $userId, string $mode, int $amount = 1): array
    {
        $mode = strtolower($mode);
        
        // 验证输入
        if (!in_array($mode, ['dag', 'agent'])) {
            return [
                'success' => false,
                'remaining' => 0,
                'message' => '无效的额度类型',
            ];
        }
        
        if ($amount <= 0) {
            return [
                'success' => false,
                'remaining' => 0,
                'message' => '扣减数量必须为正数',
            ];
        }
        
        try {
            DB::beginTransaction();
            
            // 获取用户额度记录（加锁）
            $credits = UserCredit::where('user_id', $userId)->lockForUpdate()->first();
            
            if (!$credits) {
                $credits = UserCredit::getOrCreate($userId);
                $credits = UserCredit::where('user_id', $userId)->lockForUpdate()->first();
            }
            
            // 检查余额是否足够（不变量检查）
            $currentBalance = $mode === 'dag' ? $credits->dag_credits : $credits->agent_credits;
            
            if ($currentBalance < $amount) {
                DB::rollBack();
                return [
                    'success' => false,
                    'remaining' => $currentBalance,
                    'message' => "额度不足，当前{$mode}额度：{$currentBalance}",
                ];
            }
            
            // 执行扣减
            $deducted = $mode === 'dag' 
                ? $credits->deductDagCredits($amount)
                : $credits->deductAgentCredits($amount);
            
            if (!$deducted) {
                DB::rollBack();
                return [
                    'success' => false,
                    'remaining' => $currentBalance,
                    'message' => '扣减失败，请稍后重试',
                ];
            }
            
            // 刷新获取最新值
            $credits->refresh();
            $newBalance = $mode === 'dag' ? $credits->dag_credits : $credits->agent_credits;
            
            // 验证不变量：余额不能为负
            if ($newBalance < 0) {
                DB::rollBack();
                Log::error('额度扣减后余额为负，违反不变量', [
                    'user_id' => $userId,
                    'mode' => $mode,
                    'amount' => $amount,
                    'new_balance' => $newBalance,
                ]);
                return [
                    'success' => false,
                    'remaining' => $currentBalance,
                    'message' => '系统错误：余额计算异常',
                ];
            }
            
            // 记录扣减日志
            $dagAmount = $mode === 'dag' ? -$amount : 0;
            $agentAmount = $mode === 'agent' ? -$amount : 0;
            
            CreditLog::createLog(
                $userId,
                $dagAmount,
                $agentAmount,
                '系统自动扣减（超出每日限额）',
                null
            );
            
            DB::commit();
            
            Log::info('额度扣减成功', [
                'user_id' => $userId,
                'mode' => $mode,
                'amount' => $amount,
                'remaining' => $newBalance,
            ]);
            
            return [
                'success' => true,
                'remaining' => $newBalance,
                'message' => "扣减成功，剩余{$mode}额度：{$newBalance}",
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('额度扣减失败', [
                'user_id' => $userId,
                'mode' => $mode,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'remaining' => 0,
                'message' => '系统错误，请稍后重试',
            ];
        }
    }

    /**
     * 批量添加额度（管理员操作）
     * 
     * @param array $userIds 用户ID数组
     * @param int $dagCredits DAG额度增加量
     * @param int $agentCredits Agent额度增加量
     * @param string $reason 添加原因
     * @param int|null $adminId 操作管理员ID
     * @return array 操作结果
     */
    public function batchAddCredits(
        array $userIds,
        int $dagCredits,
        int $agentCredits,
        string $reason,
        ?int $adminId = null
    ): array {
        $results = [
            'success_count' => 0,
            'fail_count' => 0,
            'failed_users' => [],
        ];
        
        foreach ($userIds as $userId) {
            $result = $this->addCredits($userId, $dagCredits, $agentCredits, $reason, $adminId);
            
            if ($result['success']) {
                $results['success_count']++;
            } else {
                $results['fail_count']++;
                $results['failed_users'][] = [
                    'user_id' => $userId,
                    'message' => $result['message'],
                ];
            }
        }
        
        Log::info('批量添加额度完成', [
            'total_users' => count($userIds),
            'success_count' => $results['success_count'],
            'fail_count' => $results['fail_count'],
            'admin_id' => $adminId,
        ]);
        
        return $results;
    }

    /**
     * 获取用户额度变更历史
     * 
     * @param int $userId 用户ID
     * @param int $limit 返回条数
     * @return array
     */
    public function getCreditHistory(int $userId, int $limit = 50): array
    {
        $logs = CreditLog::getUserLogs($userId, $limit);
        
        return $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'dag_amount' => $log->dag_amount,
                'agent_amount' => $log->agent_amount,
                'reason' => $log->reason,
                'admin_name' => $log->admin?->name ?? '系统',
                'type' => $log->getTypeDescription(),
                'created_at' => $log->created_at->toDateTimeString(),
            ];
        })->toArray();
    }

    /**
     * 检查用户是否有足够的额度
     * 
     * @param int $userId 用户ID
     * @param string $mode 额度类型：'dag' 或 'agent'
     * @param int $amount 需要的数量
     * @return bool
     */
    public function hasEnoughCredits(int $userId, string $mode, int $amount = 1): bool
    {
        $credits = $this->getCredits($userId);
        
        $mode = strtolower($mode);
        $key = "{$mode}_credits";
        
        return isset($credits[$key]) && $credits[$key] >= $amount;
    }

    /**
     * 获取系统额度统计（管理员用）
     * 
     * @return array
     */
    public function getSystemCreditStats(): array
    {
        $totalDag = UserCredit::sum('dag_credits');
        $totalAgent = UserCredit::sum('agent_credits');
        $usersWithCredits = UserCredit::where('dag_credits', '>', 0)
            ->orWhere('agent_credits', '>', 0)
            ->count();
        
        return [
            'total_dag_credits' => $totalDag,
            'total_agent_credits' => $totalAgent,
            'users_with_credits' => $usersWithCredits,
        ];
    }
}
