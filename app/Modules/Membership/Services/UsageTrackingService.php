<?php

namespace App\Modules\Membership\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * 用量追踪服务
 * 
 * 追踪用户的AI对话次数（DAG模式和Agent模式分开统计）
 * 支持打赏后管理员手动添加额外次数
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 */
class UsageTrackingService
{
    /**
     * 默认每日限制
     */
    const DEFAULT_DAG_LIMIT = 10;      // DAG模式每日10次
    const DEFAULT_AGENT_LIMIT = 3;     // Agent模式每日3次
    
    /**
     * 缓存键前缀
     */
    const CACHE_PREFIX = 'usage:';
    const CACHE_TTL = 3600; // 1小时
    
    /**
     * 检查用户是否可以执行查询
     * 
     * @param int $userId 用户ID
     * @param string $strategy 策略（dag或agent）
     * @return array ['allowed' => bool, 'remaining' => int, 'limit' => int, 'used' => int, 'message' => string]
     */
    public function checkLimit(int $userId, string $strategy = 'dag'): array
    {
        $today = Carbon::today()->toDateString();
        
        // 获取或创建今日统计
        $stats = $this->getOrCreateDailyStats($userId, $today);
        $bonusCredits = $this->getBonusCredits($userId);
        
        if ($strategy === 'agent') {
            $used = $stats->agent_queries;
            $limit = $stats->agent_limit;
            $bonusTotal = $bonusCredits->total_agent_credits ?? 0;
            $bonusUsed = $bonusCredits->used_agent_credits ?? 0;
        } else {
            $used = $stats->dag_queries;
            $limit = $stats->dag_limit;
            $bonusTotal = $bonusCredits->total_dag_credits ?? 0;
            $bonusUsed = $bonusCredits->used_dag_credits ?? 0;
        }
        
        // 计算剩余次数
        $bonusRemaining = max(0, $bonusTotal - $bonusUsed);
        $dailyRemaining = $limit == -1 ? PHP_INT_MAX : max(0, $limit - $used);
        $totalRemaining = $dailyRemaining + $bonusRemaining;
        
        // 无限制
        if ($limit == -1) {
            return [
                'allowed' => true,
                'remaining' => -1,
                'limit' => -1,
                'used' => $used,
                'bonus_remaining' => $bonusRemaining,
                'message' => '无限制'
            ];
        }
        
        // 检查是否允许
        $allowed = $totalRemaining > 0;
        
        $message = $allowed 
            ? "今日剩余 {$dailyRemaining} 次" . ($bonusRemaining > 0 ? "，额外 {$bonusRemaining} 次" : "")
            : "今日次数已用完" . ($bonusRemaining > 0 ? "，额外次数也已用完" : "，打赏可获得更多次数");
        
        return [
            'allowed' => $allowed,
            'remaining' => $totalRemaining,
            'daily_remaining' => $dailyRemaining,
            'bonus_remaining' => $bonusRemaining,
            'limit' => $limit,
            'used' => $used,
            'message' => $message
        ];
    }
    
    /**
     * 记录一次查询
     * 
     * @param int $userId 用户ID
     * @param string $strategy 策略（dag或agent）
     * @param array $metadata 元数据
     * @return bool 是否成功
     */
    public function recordQuery(int $userId, string $strategy = 'dag', array $metadata = []): bool
    {
        $today = Carbon::today()->toDateString();
        
        try {
            DB::beginTransaction();
            
            // 获取或创建今日统计
            $stats = $this->getOrCreateDailyStats($userId, $today);
            $bonusCredits = $this->getBonusCredits($userId);
            
            // 确定使用哪种次数
            if ($strategy === 'agent') {
                $dailyRemaining = $stats->agent_limit == -1 ? PHP_INT_MAX : max(0, $stats->agent_limit - $stats->agent_queries);
                $bonusRemaining = max(0, ($bonusCredits->total_agent_credits ?? 0) - ($bonusCredits->used_agent_credits ?? 0));
                
                if ($dailyRemaining > 0) {
                    // 使用每日次数
                    DB::table('user_usage_stats')
                        ->where('id', $stats->id)
                        ->increment('agent_queries');
                } elseif ($bonusRemaining > 0) {
                    // 使用额外次数
                    DB::table('user_bonus_credits')
                        ->where('user_id', $userId)
                        ->increment('used_agent_credits');
                } else {
                    DB::rollBack();
                    return false;
                }
            } else {
                $dailyRemaining = $stats->dag_limit == -1 ? PHP_INT_MAX : max(0, $stats->dag_limit - $stats->dag_queries);
                $bonusRemaining = max(0, ($bonusCredits->total_dag_credits ?? 0) - ($bonusCredits->used_dag_credits ?? 0));
                
                if ($dailyRemaining > 0) {
                    // 使用每日次数
                    DB::table('user_usage_stats')
                        ->where('id', $stats->id)
                        ->increment('dag_queries');
                } elseif ($bonusRemaining > 0) {
                    // 使用额外次数
                    DB::table('user_bonus_credits')
                        ->where('user_id', $userId)
                        ->increment('used_dag_credits');
                } else {
                    DB::rollBack();
                    return false;
                }
            }
            
            // 更新元数据
            if (!empty($metadata)) {
                $existingMetadata = json_decode($stats->metadata ?? '{}', true);
                $mergedMetadata = array_merge($existingMetadata, $metadata);
                
                DB::table('user_usage_stats')
                    ->where('id', $stats->id)
                    ->update(['metadata' => json_encode($mergedMetadata)]);
            }
            
            DB::commit();
            
            // 清除缓存
            $this->clearCache($userId);
            
            Log::info("用量记录成功", [
                'user_id' => $userId,
                'strategy' => $strategy,
                'date' => $today
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("用量记录失败", [
                'user_id' => $userId,
                'strategy' => $strategy,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 添加额外次数（管理员操作）
     * 
     * @param int $userId 用户ID
     * @param int $dagCredits DAG额外次数
     * @param int $agentCredits Agent额外次数
     * @param string $reason 原因（如打赏金额）
     * @return bool 是否成功
     */
    public function addBonusCredits(int $userId, int $dagCredits = 0, int $agentCredits = 0, string $reason = ''): bool
    {
        try {
            $bonusCredits = $this->getBonusCredits($userId);
            
            // 更新历史记录
            $history = json_decode($bonusCredits->donation_history ?? '[]', true);
            $history[] = [
                'date' => Carbon::now()->toDateTimeString(),
                'dag_credits' => $dagCredits,
                'agent_credits' => $agentCredits,
                'reason' => $reason
            ];
            
            // 安全修复：使用 increment() 替代 DB::raw 变量插值
            DB::table('user_bonus_credits')
                ->where('user_id', $userId)
                ->increment('total_dag_credits', $dagCredits);

            DB::table('user_bonus_credits')
                ->where('user_id', $userId)
                ->increment('total_agent_credits', $agentCredits);

            DB::table('user_bonus_credits')
                ->where('user_id', $userId)
                ->update([
                    'donation_history' => json_encode($history),
                    'updated_at' => Carbon::now()
                ]);
            
            // 清除缓存
            $this->clearCache($userId);
            
            Log::info("添加额外次数成功", [
                'user_id' => $userId,
                'dag_credits' => $dagCredits,
                'agent_credits' => $agentCredits,
                'reason' => $reason
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("添加额外次数失败", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 获取用户用量统计
     * 
     * @param int $userId 用户ID
     * @return array 用量统计
     */
    public function getUserUsageStats(int $userId): array
    {
        $today = Carbon::today()->toDateString();
        $stats = $this->getOrCreateDailyStats($userId, $today);
        $bonusCredits = $this->getBonusCredits($userId);
        
        return [
            'date' => $today,
            'dag' => [
                'used' => $stats->dag_queries,
                'limit' => $stats->dag_limit,
                'remaining' => $stats->dag_limit == -1 ? -1 : max(0, $stats->dag_limit - $stats->dag_queries)
            ],
            'agent' => [
                'used' => $stats->agent_queries,
                'limit' => $stats->agent_limit,
                'remaining' => $stats->agent_limit == -1 ? -1 : max(0, $stats->agent_limit - $stats->agent_queries)
            ],
            'bonus' => [
                'dag_total' => $bonusCredits->total_dag_credits ?? 0,
                'dag_used' => $bonusCredits->used_dag_credits ?? 0,
                'dag_remaining' => max(0, ($bonusCredits->total_dag_credits ?? 0) - ($bonusCredits->used_dag_credits ?? 0)),
                'agent_total' => $bonusCredits->total_agent_credits ?? 0,
                'agent_used' => $bonusCredits->used_agent_credits ?? 0,
                'agent_remaining' => max(0, ($bonusCredits->total_agent_credits ?? 0) - ($bonusCredits->used_agent_credits ?? 0))
            ]
        ];
    }
    
    /**
     * 获取或创建每日统计记录
     */
    private function getOrCreateDailyStats(int $userId, string $date): object
    {
        $stats = DB::table('user_usage_stats')
            ->where('user_id', $userId)
            ->where('date', $date)
            ->first();
        
        if (!$stats) {
            // 创建新记录
            $id = DB::table('user_usage_stats')->insertGetId([
                'user_id' => $userId,
                'date' => $date,
                'dag_queries' => 0,
                'dag_limit' => self::DEFAULT_DAG_LIMIT,
                'agent_queries' => 0,
                'agent_limit' => self::DEFAULT_AGENT_LIMIT,
                'bonus_dag_queries' => 0,
                'bonus_agent_queries' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            
            $stats = DB::table('user_usage_stats')->find($id);
        }
        
        return $stats;
    }
    
    /**
     * 获取用户额外次数记录
     */
    private function getBonusCredits(int $userId): object
    {
        $credits = DB::table('user_bonus_credits')
            ->where('user_id', $userId)
            ->first();
        
        if (!$credits) {
            // 创建新记录
            DB::table('user_bonus_credits')->insert([
                'user_id' => $userId,
                'total_dag_credits' => 0,
                'total_agent_credits' => 0,
                'used_dag_credits' => 0,
                'used_agent_credits' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            
            $credits = DB::table('user_bonus_credits')
                ->where('user_id', $userId)
                ->first();
        }
        
        return $credits;
    }
    
    /**
     * 清除用户缓存
     */
    private function clearCache(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . $userId);
    }
}
