<?php

namespace App\Services;

use App\Models\UsageStat;
use App\Models\UserCredit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * UsageService - 用量管理服务
 * 
 * 负责用户每日AI查询用量的追踪、限制检查和计数管理
 * 
 * 核心功能：
 * - 获取今日用量统计
 * - 检查是否可以执行查询（考虑每日限制和额外额度）
 * - 增加用量计数
 * - 重置每日用量（定时任务）
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 2.1-2.6, 4.1-4.6
 */
class UsageService
{
    /**
     * 默认每日限制（开发测试阶段）
     * 当会员系统禁用时使用这些限制
     */
    const DEFAULT_DAG_LIMIT = 10;
    const DEFAULT_AGENT_LIMIT = 3;

    /**
     * 免费用户每日限制
     */
    const FREE_DAG_LIMIT = 5;
    const FREE_AGENT_LIMIT = 0;

    /**
     * 暖心会员每日限制
     */
    const WARMHEART_DAG_LIMIT = 10;
    const WARMHEART_AGENT_LIMIT = 0;

    /**
     * 能量会员每日限制
     */
    const ENERGY_DAG_LIMIT = 999999;
    const ENERGY_AGENT_LIMIT = 999999;

    /**
     * @var MembershipService
     */
    protected $membershipService;

    /**
     * @var CreditService
     */
    protected $creditService;

    /**
     * 构造函数
     */
    public function __construct()
    {
        // 延迟加载服务，避免循环依赖
    }

    /**
     * 获取MembershipService实例
     */
    protected function getMembershipService(): MembershipService
    {
        if (!$this->membershipService) {
            $this->membershipService = app(MembershipService::class);
        }
        return $this->membershipService;
    }

    /**
     * 获取CreditService实例
     */
    protected function getCreditService(): CreditService
    {
        if (!$this->creditService) {
            $this->creditService = app(CreditService::class);
        }
        return $this->creditService;
    }

    /**
     * 获取用户今日用量统计
     * 
     * @param int $userId 用户ID
     * @return array 用量统计数据
     * 
     * 返回格式：
     * [
     *     'dag_used' => int,        // 今日已使用DAG次数
     *     'dag_limit' => int,       // DAG每日限制
     *     'dag_remaining' => int,   // DAG剩余次数
     *     'agent_used' => int,      // 今日已使用Agent次数
     *     'agent_limit' => int,     // Agent每日限制
     *     'agent_remaining' => int, // Agent剩余次数
     *     'dag_credits' => int,     // DAG额外额度
     *     'agent_credits' => int,   // Agent额外额度
     *     'date' => string,         // 统计日期
     * ]
     */
    public function getTodayUsage(int $userId): array
    {
        // 获取或创建今日用量记录
        $usage = UsageStat::getOrCreateTodayUsage($userId);
        
        // 获取用户的有效限制
        $limits = $this->getMembershipService()->getEffectiveLimits($userId);
        
        // 获取额外额度
        $credits = $this->getCreditService()->getCredits($userId);
        
        // 计算剩余次数（每日限制 - 已使用 + 额外额度）
        $dagRemaining = max(0, $limits['daily_dag_limit'] - $usage->dag_queries) + $credits['dag_credits'];
        $agentRemaining = max(0, $limits['daily_agent_limit'] - $usage->agent_queries) + $credits['agent_credits'];
        
        return [
            'dag_used' => $usage->dag_queries,
            'dag_limit' => $limits['daily_dag_limit'],
            'dag_remaining' => $dagRemaining,
            'agent_used' => $usage->agent_queries,
            'agent_limit' => $limits['daily_agent_limit'],
            'agent_remaining' => $agentRemaining,
            'dag_credits' => $credits['dag_credits'],
            'agent_credits' => $credits['agent_credits'],
            'date' => $usage->date->toDateString(),
        ];
    }

    /**
     * 检查用户是否可以执行查询
     * 
     * @param int $userId 用户ID
     * @param string $mode 查询模式：'dag' 或 'agent'
     * @return array 检查结果
     * 
     * 返回格式：
     * [
     *     'allowed' => bool,        // 是否允许执行
     *     'remaining' => int,       // 剩余次数
     *     'use_credits' => bool,    // 是否需要使用额外额度
     *     'message' => string,      // 提示消息
     * ]
     */
    public function canExecuteQuery(int $userId, string $mode): array
    {
        $mode = strtolower($mode);
        
        if (!in_array($mode, ['dag', 'agent'])) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'use_credits' => false,
                'message' => '无效的查询模式',
            ];
        }
        
        // 获取今日用量
        $todayUsage = $this->getTodayUsage($userId);
        
        $usedKey = "{$mode}_used";
        $limitKey = "{$mode}_limit";
        $remainingKey = "{$mode}_remaining";
        $creditsKey = "{$mode}_credits";
        
        $used = $todayUsage[$usedKey];
        $limit = $todayUsage[$limitKey];
        $remaining = $todayUsage[$remainingKey];
        $credits = $todayUsage[$creditsKey];
        
        // 检查是否还有剩余次数
        if ($remaining <= 0) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'use_credits' => false,
                'message' => $this->getQuotaExceededMessage($mode),
            ];
        }
        
        // 检查是否需要使用额外额度
        $dailyRemaining = max(0, $limit - $used);
        $useCredits = $dailyRemaining <= 0 && $credits > 0;
        
        return [
            'allowed' => true,
            'remaining' => $remaining,
            'use_credits' => $useCredits,
            'message' => $useCredits 
                ? "每日限额已用完，将使用额外额度（剩余{$credits}次）" 
                : "可以执行查询（剩余{$remaining}次）",
        ];
    }

    /**
     * 增加用量计数
     * 
     * @param int $userId 用户ID
     * @param string $mode 查询模式：'dag' 或 'agent'
     * @return array 操作结果
     * 
     * 返回格式：
     * [
     *     'success' => bool,        // 是否成功
     *     'used_credits' => bool,   // 是否使用了额外额度
     *     'new_count' => int,       // 新的使用次数
     *     'remaining' => int,       // 剩余次数
     *     'message' => string,      // 提示消息
     * ]
     */
    public function incrementUsage(int $userId, string $mode): array
    {
        $mode = strtolower($mode);
        
        if (!in_array($mode, ['dag', 'agent'])) {
            return [
                'success' => false,
                'used_credits' => false,
                'new_count' => 0,
                'remaining' => 0,
                'message' => '无效的查询模式',
            ];
        }
        
        // 先检查是否可以执行
        $canExecute = $this->canExecuteQuery($userId, $mode);
        
        if (!$canExecute['allowed']) {
            return [
                'success' => false,
                'used_credits' => false,
                'new_count' => 0,
                'remaining' => 0,
                'message' => $canExecute['message'],
            ];
        }
        
        $usedCredits = false;
        
        try {
            DB::beginTransaction();
            
            // 获取今日用量记录
            $usage = UsageStat::getOrCreateTodayUsage($userId);
            $limits = $this->getMembershipService()->getEffectiveLimits($userId);
            
            $limitKey = "daily_{$mode}_limit";
            $limit = $limits[$limitKey];
            
            // 检查是否需要使用额外额度
            $currentUsed = $mode === 'dag' ? $usage->dag_queries : $usage->agent_queries;
            
            if ($currentUsed >= $limit) {
                // 每日限额已用完，需要扣减额外额度
                $deducted = $this->getCreditService()->deductCredits($userId, $mode);
                
                if (!$deducted['success']) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'used_credits' => false,
                        'new_count' => $currentUsed,
                        'remaining' => 0,
                        'message' => '额度不足，无法执行查询',
                    ];
                }
                
                $usedCredits = true;
            }
            
            // 增加用量计数
            if ($mode === 'dag') {
                $newCount = $usage->incrementDagQueries();
            } else {
                $newCount = $usage->incrementAgentQueries();
            }
            
            DB::commit();
            
            // 重新获取剩余次数
            $todayUsage = $this->getTodayUsage($userId);
            $remaining = $todayUsage["{$mode}_remaining"];
            
            Log::info('用量计数已增加', [
                'user_id' => $userId,
                'mode' => $mode,
                'new_count' => $newCount,
                'used_credits' => $usedCredits,
                'remaining' => $remaining,
            ]);
            
            return [
                'success' => true,
                'used_credits' => $usedCredits,
                'new_count' => $newCount,
                'remaining' => $remaining,
                'message' => $usedCredits 
                    ? "已使用额外额度（剩余{$remaining}次）" 
                    : "查询成功（剩余{$remaining}次）",
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('增加用量计数失败', [
                'user_id' => $userId,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'used_credits' => false,
                'new_count' => 0,
                'remaining' => 0,
                'message' => '系统错误，请稍后重试',
            ];
        }
    }

    /**
     * 重置所有用户的每日用量（定时任务）
     * 
     * 注意：此方法不会删除历史记录，只是新的一天会自动创建新记录
     * 旧记录保留用于统计分析
     * 
     * @return array 操作结果
     */
    public function resetDailyUsage(): array
    {
        try {
            // 实际上不需要做任何事情
            // 因为每天的用量记录是按日期分开的
            // 新的一天会自动创建新的记录
            
            Log::info('每日用量重置任务执行', [
                'date' => Carbon::today()->toDateString(),
            ]);
            
            return [
                'success' => true,
                'message' => '每日用量已重置',
                'date' => Carbon::today()->toDateString(),
            ];
            
        } catch (\Exception $e) {
            Log::error('每日用量重置失败', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => '重置失败：' . $e->getMessage(),
            ];
        }
    }

    /**
     * 获取用量超限提示消息
     * 
     * @param string $mode 查询模式
     * @return string
     */
    protected function getQuotaExceededMessage(string $mode): string
    {
        if ($mode === 'dag') {
            return '今日DAG查询次数已用完，请明天再试或升级会员获取更多次数';
        }
        
        return '今日Agent查询次数已用完，请明天再试或升级会员获取更多次数';
    }

    /**
     * 获取用户的用量历史统计
     * 
     * @param int $userId 用户ID
     * @param int $days 统计天数
     * @return array
     */
    public function getUsageHistory(int $userId, int $days = 30): array
    {
        $startDate = Carbon::today()->subDays($days - 1);
        
        $stats = UsageStat::where('user_id', $userId)
            ->where('date', '>=', $startDate)
            ->orderBy('date', 'asc')
            ->get();
        
        $totalDag = $stats->sum('dag_queries');
        $totalAgent = $stats->sum('agent_queries');
        $avgDag = $stats->count() > 0 ? round($totalDag / $stats->count(), 1) : 0;
        $avgAgent = $stats->count() > 0 ? round($totalAgent / $stats->count(), 1) : 0;
        
        return [
            'period_days' => $days,
            'total_dag_queries' => $totalDag,
            'total_agent_queries' => $totalAgent,
            'avg_dag_per_day' => $avgDag,
            'avg_agent_per_day' => $avgAgent,
            'daily_stats' => $stats->map(function ($stat) {
                return [
                    'date' => $stat->date->toDateString(),
                    'dag_queries' => $stat->dag_queries,
                    'agent_queries' => $stat->agent_queries,
                ];
            })->toArray(),
        ];
    }

    /**
     * 检查用户是否接近用量限制（用于前端警告）
     * 
     * @param int $userId 用户ID
     * @param int $threshold 警告阈值（剩余次数）
     * @return array
     */
    public function checkLowUsageWarning(int $userId, int $threshold = 2): array
    {
        $todayUsage = $this->getTodayUsage($userId);
        
        $warnings = [];
        
        if ($todayUsage['dag_remaining'] <= $threshold && $todayUsage['dag_remaining'] > 0) {
            $warnings[] = [
                'type' => 'dag',
                'remaining' => $todayUsage['dag_remaining'],
                'message' => "DAG查询剩余{$todayUsage['dag_remaining']}次，即将用完",
            ];
        }
        
        if ($todayUsage['agent_remaining'] <= $threshold && $todayUsage['agent_remaining'] > 0) {
            $warnings[] = [
                'type' => 'agent',
                'remaining' => $todayUsage['agent_remaining'],
                'message' => "Agent查询剩余{$todayUsage['agent_remaining']}次，即将用完",
            ];
        }
        
        return [
            'has_warning' => count($warnings) > 0,
            'warnings' => $warnings,
        ];
    }
}
