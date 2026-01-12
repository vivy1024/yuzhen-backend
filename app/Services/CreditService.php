<?php

namespace App\Services;

use App\Models\UserCredit;
use App\Models\CreditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CreditService - 额度管理服务
 * 
 * 负责用户额外次数（打赏奖励）的管理
 * 
 * 核心功能：
 * - 获取用户额度
 * - 添加额度（管理员操作）
 * - 扣减额度（带事务和不变量检查）
 * 
 * 不变量：
 * - 额度余额永远不能为负数
 * - 所有额度变更必须记录日志
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 3.1-3.6
 */
class CreditService
{
    /**
     * 获取用户额度
     * 
     * @param int $userId 用户ID
     * @return array 额度信息
     * 
     * 返回格式：
     * [
     *     'dag_credits' => int,     // DAG额外次数
     *     'agent_credits' => int,   // Agent额外次数
     *     'total_credits' => int,   // 总额度
     * ]
     */
    public function getCredits(int $userId): array
    {
        $credits = UserCredit::getOrCreate($userId);
        
        return [
            'dag_credits' => $credits->dag_credits,
            'agent_credits' => $credits->agent_credits,
            'total_credits' => $credits->getTotalCredits(),
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
