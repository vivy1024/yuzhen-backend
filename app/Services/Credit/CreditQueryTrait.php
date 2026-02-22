<?php

namespace App\Services\Credit;

use App\Models\UserCredit;
use App\Models\CreditLog;
use Illuminate\Support\Facades\Log;

/**
 * CreditQueryTrait - 积分只读查询
 *
 * 所有只读查询方法：
 * - getBalance: 获取积分余额详情
 * - getCredits: 获取额度（旧方法，兼容）
 * - checkSufficientCredits: 检查积分是否足够
 * - hasEnoughCredits: 简单余额检查
 * - getCreditHistory: 额度变更历史
 * - getSystemCreditStats: 系统统计（管理员）
 */
trait CreditQueryTrait
{
    /**
     * 获取用户积分余额
     */
    public function getBalance(int $userId): array
    {
        $membershipTier = $this->getUserMembershipTier($userId);
        $userCredit = UserCredit::getOrCreate($userId, $membershipTier);

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
            'is_mvp_phase' => false,
            'low_balance_warning' => $userCredit->isLowBalance(),
            'last_reset' => $userCredit->last_reset_date->toDateString(),
        ];
    }

    /**
     * 检查用户积分是否足够
     */
    public function checkSufficientCredits(int $userId, int $requiredCredits): array
    {
        $membershipTier = $this->getUserMembershipTier($userId);
        $userCredit = UserCredit::getOrCreate($userId, $membershipTier);

        if ($userCredit->needsReset()) {
            $this->resetDailyQuota($userId);
            $userCredit->refresh();
        }

        $remaining = $userCredit->remaining;
        $sufficient = $remaining >= $requiredCredits;

        if ($sufficient) {
            return [
                'sufficient' => true,
                'message' => '积分充足',
                'remaining' => $remaining,
            ];
        }

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
     * 获取用户额度（旧方法，保留兼容性）
     *
     * @deprecated 使用 getBalance() 替代
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
     * 检查用户是否有足够的额度
     */
    public function hasEnoughCredits(int $userId, string $mode, int $amount = 1): bool
    {
        $credits = $this->getCredits($userId);

        $mode = strtolower($mode);
        $key = "{$mode}_credits";

        return isset($credits[$key]) && $credits[$key] >= $amount;
    }

    /**
     * 获取用户额度变更历史
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
     * 获取系统额度统计（管理员用）
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
