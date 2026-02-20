<?php

namespace App\Modules\Admin\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\User;
use App\Models\UsageStat;
use App\Models\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * 运营 KPI 控制器
 *
 * 提供 DAU/MAU、用户增长、留存率、对话量等聚合指标
 * 供管理员 Dashboard 和 OpenClaw Agent 使用
 */
class AdminKpiController extends BaseController
{
    /**
     * 今日运营概览
     *
     * GET /api/admin/kpi/overview
     */
    public function overview(): JsonResponse
    {
        try {
            $today = Carbon::today();

            // DAU
            $dau = UsageStat::where('date', $today)
                ->distinct('user_id')->count('user_id');

            // MAU (30天)
            $mau = UsageStat::where('date', '>=', $today->copy()->subDays(30))
                ->distinct('user_id')->count('user_id');

            // 今日新增用户
            $newUsersToday = User::whereDate('created_at', $today)->count();

            // 今日对话量
            $todayQueries = UsageStat::where('date', $today)
                ->selectRaw('COALESCE(SUM(dag_queries), 0) as dag, COALESCE(SUM(agent_queries), 0) as agent')
                ->first();

            // 总用户数 & 会员数
            $totalUsers = User::count();
            $totalMembers = User::where('membership_tier', '!=', 'free')
                ->whereNotNull('membership_tier')
                ->count();

            // 今日收入
            $todayRevenue = DB::table('membership_orders')
                ->where('status', 'paid')
                ->whereDate('created_at', $today)
                ->sum('amount');

            // 粘性 DAU/MAU
            $stickiness = $mau > 0 ? round($dau / $mau * 100, 1) : 0;

            return $this->success([
                'dau' => $dau,
                'mau' => $mau,
                'stickiness_pct' => $stickiness,
                'new_users_today' => $newUsersToday,
                'total_users' => $totalUsers,
                'active_members' => $totalMembers,
                'today_queries' => [
                    'dag' => (int) $todayQueries->dag,
                    'agent' => (int) $todayQueries->agent,
                    'total' => (int) $todayQueries->dag + (int) $todayQueries->agent,
                ],
                'today_revenue' => round((float) $todayRevenue, 2),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, '获取KPI概览');
        }
    }

    /**
     * 用户增长趋势
     *
     * GET /api/admin/kpi/growth?period=7d|30d|90d
     */
    public function growth(Request $request): JsonResponse
    {
        try {
            $days = $this->parsePeriod($request->get('period', '30d'));
            $startDate = Carbon::today()->subDays($days);

            // 每日新增
            $dailyGrowth = User::where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as new_users')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // 累计用户数（每日快照）
            $totalBefore = User::where('created_at', '<', $startDate)->count();
            $cumulative = [];
            $running = $totalBefore;
            foreach ($dailyGrowth as $row) {
                $running += $row->new_users;
                $cumulative[] = [
                    'date' => $row->date,
                    'new_users' => $row->new_users,
                    'total_users' => $running,
                ];
            }

            // 会员转化率
            $periodNewUsers = User::where('created_at', '>=', $startDate)->count();
            $periodNewMembers = User::where('created_at', '>=', $startDate)
                ->where('membership_tier', '!=', 'free')
                ->whereNotNull('membership_tier')
                ->count();

            return $this->success([
                'period' => $request->get('period', '30d'),
                'daily' => $cumulative,
                'summary' => [
                    'total_new' => $periodNewUsers,
                    'total_members' => $periodNewMembers,
                    'conversion_rate_pct' => $periodNewUsers > 0
                        ? round($periodNewMembers / $periodNewUsers * 100, 1) : 0,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, '获取用户增长趋势');
        }
    }

    /**
     * DAU/MAU 活跃度趋势
     *
     * GET /api/admin/kpi/activity?period=7d|30d|90d
     */
    public function activity(Request $request): JsonResponse
    {
        try {
            $days = $this->parsePeriod($request->get('period', '30d'));
            $startDate = Carbon::today()->subDays($days);

            // 每日 DAU + 对话量
            $daily = UsageStat::where('date', '>=', $startDate)
                ->selectRaw('date, COUNT(DISTINCT user_id) as dau, SUM(dag_queries) as dag, SUM(agent_queries) as agent')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn($row) => [
                    'date' => $row->date->format('Y-m-d'),
                    'dau' => $row->dau,
                    'queries' => [
                        'dag' => (int) $row->dag,
                        'agent' => (int) $row->agent,
                        'total' => (int) $row->dag + (int) $row->agent,
                    ],
                    'avg_queries_per_user' => $row->dau > 0
                        ? round(((int) $row->dag + (int) $row->agent) / $row->dau, 1) : 0,
                ]);

            // 周活 WAU
            $wau = UsageStat::where('date', '>=', Carbon::today()->subDays(7))
                ->distinct('user_id')->count('user_id');

            // 月活 MAU
            $mau = UsageStat::where('date', '>=', Carbon::today()->subDays(30))
                ->distinct('user_id')->count('user_id');

            return $this->success([
                'period' => $request->get('period', '30d'),
                'daily' => $daily,
                'summary' => [
                    'wau' => $wau,
                    'mau' => $mau,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, '获取活跃度趋势');
        }
    }

    /**
     * 用户留存率（Cohort 分析）
     *
     * GET /api/admin/kpi/retention?cohort_days=30
     */
    public function retention(Request $request): JsonResponse
    {
        try {
            $cohortDays = min((int) $request->get('cohort_days', 30), 90);

            // 7日留存
            $retention7d = DB::select("
                SELECT
                    DATE(u.created_at) AS cohort_date,
                    COUNT(DISTINCT u.id) AS new_users,
                    COUNT(DISTINCT s.user_id) AS retained,
                    ROUND(COUNT(DISTINCT s.user_id) * 100.0 / NULLIF(COUNT(DISTINCT u.id), 0), 1) AS retention_pct
                FROM users u
                LEFT JOIN usage_stats s
                    ON s.user_id = u.id
                    AND s.date = DATE(u.created_at) + INTERVAL 7 DAY
                WHERE u.created_at >= ? AND u.created_at <= ?
                GROUP BY DATE(u.created_at)
                ORDER BY cohort_date
            ", [
                Carbon::today()->subDays($cohortDays)->toDateString(),
                Carbon::today()->subDays(7)->toDateString(),
            ]);

            // 30日留存
            $retention30d = DB::select("
                SELECT
                    DATE(u.created_at) AS cohort_date,
                    COUNT(DISTINCT u.id) AS new_users,
                    COUNT(DISTINCT s.user_id) AS retained,
                    ROUND(COUNT(DISTINCT s.user_id) * 100.0 / NULLIF(COUNT(DISTINCT u.id), 0), 1) AS retention_pct
                FROM users u
                LEFT JOIN usage_stats s
                    ON s.user_id = u.id
                    AND s.date = DATE(u.created_at) + INTERVAL 30 DAY
                WHERE u.created_at >= ? AND u.created_at <= ?
                GROUP BY DATE(u.created_at)
                ORDER BY cohort_date
            ", [
                Carbon::today()->subDays(60)->toDateString(),
                Carbon::today()->subDays(30)->toDateString(),
            ]);

            // 平均留存率
            $avg7d = count($retention7d) > 0
                ? round(collect($retention7d)->avg('retention_pct'), 1) : 0;
            $avg30d = count($retention30d) > 0
                ? round(collect($retention30d)->avg('retention_pct'), 1) : 0;

            return $this->success([
                'retention_7d' => [
                    'cohorts' => $retention7d,
                    'average_pct' => $avg7d,
                ],
                'retention_30d' => [
                    'cohorts' => $retention30d,
                    'average_pct' => $avg30d,
                ],
                'benchmarks' => [
                    '7d_healthy' => '>25%',
                    '30d_healthy' => '>10%',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, '获取留存率');
        }
    }

    /**
     * 解析时间周期参数
     */
    private function parsePeriod(string $period): int
    {
        return match ($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };
    }
}
