<?php

namespace App\Services\Credit;

use App\Models\UserCredit;
use App\Models\CreditLog;
use App\Models\CreditTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CreditMutationTrait - 积分写入操作
 *
 * 所有涉及数据库写入的方法：
 * - recordTransaction: 记录积分消耗（带事务）
 * - resetDailyQuota: 重置每日配额
 * - addCredits: 管理员添加额度
 * - deductCredits: 扣减额度（带不变量检查）
 * - batchAddCredits: 批量添加额度
 */
trait CreditMutationTrait
{
    /**
     * 记录积分消耗
     *
     * 使用数据库事务确保原子性
     */
    public function recordTransaction(int $userId, array $data): CreditTransaction
    {
        if (!isset($data['tokens']) || !isset($data['mode'])) {
            throw new \InvalidArgumentException('tokens和mode参数是必需的');
        }

        $credits = $this->calculateCredits($data['tokens'], $data['mode']);

        return DB::transaction(function () use ($userId, $credits, $data) {
            $membershipTier = $this->getUserMembershipTier($userId);

            $userCredit = UserCredit::where('user_id', $userId)->lockForUpdate()->first();

            if (!$userCredit) {
                $userCredit = UserCredit::getOrCreate($userId, $membershipTier);
                $userCredit = UserCredit::where('user_id', $userId)->lockForUpdate()->first();
            }

            if ($userCredit->needsReset()) {
                $newQuota = self::DAILY_QUOTAS[$membershipTier] ?? self::DAILY_QUOTAS['free'];
                $userCredit->resetDailyQuota($newQuota);
            }

            if (!$userCredit->hasSufficientCredits($credits)) {
                throw new \Exception("积分不足，当前剩余: {$userCredit->remaining}，需要: {$credits}");
            }

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

            $userCredit->daily_consumed += $credits;
            $userCredit->total_consumed += $credits;
            $userCredit->save();

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
     * 重置用户每日积分配额
     */
    public function resetDailyQuota(int $userId): void
    {
        $membershipTier = $this->getUserMembershipTier($userId);
        $newQuota = self::DAILY_QUOTAS[$membershipTier] ?? self::DAILY_QUOTAS['free'];
        $userCredit = UserCredit::getOrCreate($userId, $membershipTier);

        if (!$userCredit->needsReset()) {
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

        $oldConsumed = $userCredit->daily_consumed;
        $userCredit->resetDailyQuota($newQuota);

        Log::info('用户每日积分配额已重置', [
            'user_id' => $userId,
            'membership_tier' => $membershipTier,
            'new_quota' => $newQuota,
            'previous_consumed' => $oldConsumed,
            'reset_date' => today()->toDateString(),
        ]);
    }

    /**
     * 添加额度（管理员操作）
     */
    public function addCredits(
        int $userId,
        int $dagCredits,
        int $agentCredits,
        string $reason,
        ?int $adminId = null
    ): array {
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

            $credits = UserCredit::getOrCreate($userId);

            if ($dagCredits > 0) {
                $credits->addDagCredits($dagCredits);
            }

            if ($agentCredits > 0) {
                $credits->addAgentCredits($agentCredits);
            }

            $credits->refresh();

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
     */
    public function deductCredits(int $userId, string $mode, int $amount = 1): array
    {
        $mode = strtolower($mode);

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

            $credits = UserCredit::where('user_id', $userId)->lockForUpdate()->first();

            if (!$credits) {
                $credits = UserCredit::getOrCreate($userId);
                $credits = UserCredit::where('user_id', $userId)->lockForUpdate()->first();
            }

            $currentBalance = $mode === 'dag' ? $credits->dag_credits : $credits->agent_credits;

            if ($currentBalance < $amount) {
                DB::rollBack();
                return [
                    'success' => false,
                    'remaining' => $currentBalance,
                    'message' => "额度不足，当前{$mode}额度：{$currentBalance}",
                ];
            }

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

            $credits->refresh();
            $newBalance = $mode === 'dag' ? $credits->dag_credits : $credits->agent_credits;

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
}
