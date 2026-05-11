<?php

namespace App\Modules\Credits\Services;

use App\Modules\Credits\Models\CreditLedger;
use App\Modules\Credits\Models\UserCreditAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CreditService - 积分服务
 *
 * 核心积分操作：扣减、获得、查询余额、幂等性保障
 */
class CreditService
{
    private ModelPricingService $pricingService;

    public function __construct(ModelPricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
     * 扣减积分（AI 对话后调用）
     *
     * @param int $userId 用户ID
     * @param string $amount 扣减金额（正数）
     * @param array $metadata 元数据 [model, input_tokens, output_tokens, session_id, ...]
     * @return array { success: bool, balance_after?: string, reason?: string, ledger_id?: int }
     */
    public function deduct(int $userId, string $amount, array $metadata): array
    {
        // 幂等性检查：通过 session_id + model 生成 idempotency_key
        $idempotencyKey = $this->generateIdempotencyKey($metadata);

        if ($idempotencyKey) {
            $existing = CreditLedger::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                Log::info('积分扣减跳过（幂等）', [
                    'user_id' => $userId,
                    'idempotency_key' => $idempotencyKey,
                    'existing_id' => $existing->id,
                ]);
                return [
                    'success' => true,
                    'balance_after' => $existing->balance_after,
                    'ledger_id' => $existing->id,
                    'idempotent' => true,
                ];
            }
        }

        return DB::transaction(function () use ($userId, $amount, $metadata, $idempotencyKey) {
            // 锁定账户行
            $account = UserCreditAccount::where('user_id', $userId)->lockForUpdate()->first();

            if (!$account) {
                $account = $this->getOrCreateAccount($userId);
                $account = UserCreditAccount::where('user_id', $userId)->lockForUpdate()->first();
            }

            // 检查总可用余额（balance + bonus_balance）
            $totalAvailable = bcadd($account->balance, $account->bonus_balance, 6);

            if (bccomp($totalAvailable, $amount, 6) < 0) {
                return [
                    'success' => false,
                    'reason' => 'insufficient_balance',
                    'available' => $totalAvailable,
                    'required' => $amount,
                ];
            }

            // 优先从 balance 扣减，不足部分从 bonus_balance 扣
            $remainingDeduct = $amount;

            if (bccomp($account->balance, $remainingDeduct, 6) >= 0) {
                // balance 足够
                $account->balance = bcsub($account->balance, $remainingDeduct, 6);
            } else {
                // balance 不够，差额从 bonus_balance 扣
                $fromBonus = bcsub($remainingDeduct, $account->balance, 6);
                $account->balance = '0.000000';
                $account->bonus_balance = bcsub($account->bonus_balance, $fromBonus, 6);
            }

            // 增加本月已用
            $account->used_this_month = bcadd($account->used_this_month, $amount, 6);
            $account->save();

            // 写入流水
            $balanceAfter = bcadd($account->balance, $account->bonus_balance, 6);
            $ledger = CreditLedger::create([
                'user_id' => $userId,
                'type' => CreditLedger::TYPE_SPEND,
                'amount' => '-' . $amount,
                'balance_after' => $balanceAfter,
                'model' => $metadata['model'] ?? null,
                'input_tokens' => $metadata['input_tokens'] ?? null,
                'output_tokens' => $metadata['output_tokens'] ?? null,
                'session_id' => $metadata['session_id'] ?? null,
                'source' => CreditLedger::SOURCE_AI_CHAT,
                'description' => $metadata['description'] ?? null,
                'idempotency_key' => $idempotencyKey,
            ]);

            Log::info('积分扣减成功', [
                'user_id' => $userId,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'ledger_id' => $ledger->id,
            ]);

            return [
                'success' => true,
                'balance_after' => $balanceAfter,
                'ledger_id' => $ledger->id,
                'idempotent' => false,
            ];
        });
    }

    /**
     * 获得积分（注册/签到/邀请/管理员）
     *
     * @param int $userId 用户ID
     * @param string $amount 获得金额（正数）
     * @param string $source 来源：register/checkin/invite/admin
     * @param string|null $description 描述
     * @return array { success: bool, balance_after: string, ledger_id: int }
     */
    public function earn(int $userId, string $amount, string $source, ?string $description = null): array
    {
        return DB::transaction(function () use ($userId, $amount, $source, $description) {
            $account = UserCreditAccount::where('user_id', $userId)->lockForUpdate()->first();

            if (!$account) {
                $account = $this->getOrCreateAccount($userId);
                $account = UserCreditAccount::where('user_id', $userId)->lockForUpdate()->first();
            }

            // 签到/邀请等奖励加到 bonus_balance
            if (in_array($source, [CreditLedger::SOURCE_CHECKIN, CreditLedger::SOURCE_INVITE])) {
                $account->bonus_balance = bcadd($account->bonus_balance, $amount, 6);
            } else {
                $account->balance = bcadd($account->balance, $amount, 6);
            }

            $account->save();

            $balanceAfter = bcadd($account->balance, $account->bonus_balance, 6);

            $ledger = CreditLedger::create([
                'user_id' => $userId,
                'type' => CreditLedger::TYPE_EARN,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'source' => $source,
                'description' => $description,
            ]);

            Log::info('积分获得', [
                'user_id' => $userId,
                'amount' => $amount,
                'source' => $source,
                'balance_after' => $balanceAfter,
            ]);

            return [
                'success' => true,
                'balance_after' => $balanceAfter,
                'ledger_id' => $ledger->id,
            ];
        });
    }

    /**
     * 查询余额
     *
     * @param int $userId
     * @return array
     */
    public function getBalance(int $userId): array
    {
        $account = $this->getOrCreateAccount($userId);

        return [
            'balance' => $account->balance,
            'bonus_balance' => $account->bonus_balance,
            'total_available' => bcadd($account->balance, $account->bonus_balance, 6),
            'monthly_limit' => $account->monthly_limit,
            'used_this_month' => $account->used_this_month,
            'monthly_remaining' => bcsub($account->monthly_limit, $account->used_this_month, 6),
            'tier' => $account->tier,
        ];
    }

    /**
     * 本月使用量
     *
     * @param int $userId
     * @return string
     */
    public function getUsageThisMonth(int $userId): string
    {
        $account = UserCreditAccount::where('user_id', $userId)->first();

        return $account ? $account->used_this_month : '0.000000';
    }

    /**
     * 获取或创建账户（注册时自动调用）
     *
     * @param int $userId
     * @return UserCreditAccount
     */
    public function getOrCreateAccount(int $userId): UserCreditAccount
    {
        return UserCreditAccount::firstOrCreate(
            ['user_id' => $userId],
            [
                'balance' => '100.000000',
                'monthly_limit' => '100.000000',
                'used_this_month' => '0.000000',
                'bonus_balance' => '0.000000',
                'tier' => 'free',
            ]
        );
    }

    /**
     * 检查是否有足够余额
     *
     * @param int $userId
     * @param string $amount
     * @return bool
     */
    public function hasEnoughBalance(int $userId, string $amount): bool
    {
        $account = UserCreditAccount::where('user_id', $userId)->first();

        if (!$account) {
            // 新用户默认有 100 credits
            return bccomp('100.000000', $amount, 6) >= 0;
        }

        $totalAvailable = bcadd($account->balance, $account->bonus_balance, 6);

        return bccomp($totalAvailable, $amount, 6) >= 0;
    }

    /**
     * 生成幂等性 key
     */
    private function generateIdempotencyKey(array $metadata): ?string
    {
        $sessionId = $metadata['session_id'] ?? null;

        if (!$sessionId) {
            return null;
        }

        // session_id + model 组合作为唯一标识
        $model = $metadata['model'] ?? 'unknown';
        return md5("{$sessionId}:{$model}:" . ($metadata['input_tokens'] ?? 0) . ':' . ($metadata['output_tokens'] ?? 0));
    }
}
