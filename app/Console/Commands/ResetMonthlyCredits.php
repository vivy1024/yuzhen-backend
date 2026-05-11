<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Credits\Models\UserCreditAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 月度积分重置命令
 * 
 * 每月 1 日 00:00 执行，重置所有用户的 used_this_month 为 0，
 * 并根据会员等级恢复月度配额到 balance。
 * 
 * 调度：在 Kernel.php 中注册 ->monthlyOn(1, '00:00')
 */
class ResetMonthlyCredits extends Command
{
    protected $signature = 'credits:reset-monthly {--dry-run : 仅预览不执行}';
    protected $description = '重置所有用户的月度积分使用量';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $this->info($isDryRun ? '[DRY RUN] 预览月度积分重置...' : '开始月度积分重置...');

        $accounts = UserCreditAccount::all();
        $resetCount = 0;
        $errors = 0;

        foreach ($accounts as $account) {
            try {
                $oldUsed = $account->used_this_month;
                
                if ($isDryRun) {
                    $this->line("  用户#{$account->user_id}: used_this_month {$oldUsed} → 0, balance 恢复到 {$account->monthly_limit}");
                    $resetCount++;
                    continue;
                }

                $account->update([
                    'used_this_month' => 0,
                    'balance' => $account->monthly_limit + $account->bonus_balance,
                    'last_reset_at' => now(),
                ]);
                $resetCount++;
            } catch (\Exception $e) {
                $errors++;
                Log::error("[credits:reset-monthly] 用户#{$account->user_id} 重置失败: {$e->getMessage()}");
                $this->error("  用户#{$account->user_id} 失败: {$e->getMessage()}");
            }
        }

        $this->info("完成: 重置 {$resetCount} 个账户" . ($errors ? ", {$errors} 个失败" : ''));
        
        Log::info("[credits:reset-monthly] 月度重置完成", [
            'reset_count' => $resetCount,
            'errors' => $errors,
            'dry_run' => $isDryRun,
        ]);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
