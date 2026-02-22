<?php

namespace App\Services\Credit;

/**
 * CreditCalculatorTrait - 积分计算逻辑
 *
 * 纯计算方法，无副作用：
 * - calculateCredits: Token→积分换算
 * - getUserMembershipTier: 会员等级查询
 * - getUpgradeMessage: 升级提示文案
 */
trait CreditCalculatorTrait
{
    /**
     * 计算积分消耗
     *
     * 公式：credits = ceil(tokens × multiplier / 1000)
     * - Agent模式使用1.5x倍率
     * - DAG模式使用1.0x倍率
     * - 最小消耗为1积分
     */
    public function calculateCredits(int $tokens, string $mode): int
    {
        $multiplier = strtolower($mode) === 'agent'
            ? self::AGENT_MULTIPLIER
            : self::DAG_MULTIPLIER;

        $credits = (int) ceil(($tokens * $multiplier) / self::TOKENS_PER_CREDIT);

        return max(self::MIN_CREDITS, $credits);
    }

    /**
     * 获取用户会员等级
     */
    protected function getUserMembershipTier(int $userId): string
    {
        $user = \App\Modules\User\Models\User::find($userId);

        if (!$user) {
            return 'free';
        }

        $tier = $user->membership_tier ?? 'free';

        if ($tier === 'newbie' || empty($tier)) {
            return 'free';
        }

        if (!in_array($tier, ['free', 'warmheart', 'energy'])) {
            return 'free';
        }

        return $tier;
    }

    /**
     * 获取升级提示消息
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
}
