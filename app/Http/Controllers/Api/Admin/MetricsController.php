<?php

namespace App\Http\Controllers\Api\Admin;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AdminMetricsController - 统一可观测性仪表盘聚合API
 *
 * 从 chat_sessions 表聚合查询性能指标，支持 Redis 缓存（TTL 10分钟）。
 * 所有 API 支持 ?days=7|30|90 时间范围参数。
 *
 * @version v1.0.0
 * @date 2026-02-24
 * @author 薛小川
 */
class MetricsController extends BaseController
{
    /** 缓存 TTL（秒） */
    private const CACHE_TTL = 600;

    /**
     * 系统总览
     * GET /api/admin/metrics/dashboard/system-overview
     */
    public function systemOverview(Request $request): JsonResponse
    {
        $days = $this->getDays($request);
        $cacheKey = "admin:metrics:system-overview:{$days}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($days) {
            $since = now()->subDays($days);

            // 核心指标
            $stats = ChatSession::where('created_at', '>=', $since)
                ->select(
                    DB::raw('COUNT(*) as total_requests'),
                    DB::raw('AVG(ttfb_ms) as avg_ttfb_ms'),
                    DB::raw('AVG(duration_ms) as avg_duration_ms'),
                    DB::raw("SUM(CASE WHEN error_type IS NULL OR error_type = '' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) as success_rate"),
                    DB::raw('COUNT(DISTINCT user_id) as active_users'),
                    DB::raw('SUM(credits_consumed) as total_credits'),
                    DB::raw('SUM(input_tokens + output_tokens) as total_tokens')
                )
                ->first();

            // 按天趋势
            $trend = ChatSession::where('created_at', '>=', $since)
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as requests'),
                    DB::raw('AVG(ttfb_ms) as avg_ttfb_ms'),
                    DB::raw('AVG(duration_ms) as avg_duration_ms'),
                    DB::raw("SUM(CASE WHEN error_type IS NULL OR error_type = '' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) as success_rate"),
                    DB::raw('COUNT(DISTINCT user_id) as active_users')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get();

            return [
                'stats' => $stats,
                'trend' => $trend,
            ];
        });

        return $this->success($data);
    }

    /**
     * 模型对比
     * GET /api/admin/metrics/dashboard/model-comparison
     */
    public function modelComparison(Request $request): JsonResponse
    {
        $days = $this->getDays($request);
        $cacheKey = "admin:metrics:model-comparison:{$days}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($days) {
            return ChatSession::where('created_at', '>=', now()->subDays($days))
                ->whereNotNull('backend_used')
                ->select(
                    'backend_used',
                    DB::raw('COUNT(*) as total_calls'),
                    DB::raw('AVG(ttfb_ms) as avg_ttfb_ms'),
                    DB::raw('AVG(duration_ms) as avg_duration_ms'),
                    DB::raw('SUM(input_tokens) as total_input_tokens'),
                    DB::raw('SUM(output_tokens) as total_output_tokens'),
                    DB::raw('SUM(credits_consumed) as total_credits'),
                    DB::raw('AVG(tokens_per_sec) as avg_tokens_per_sec'),
                    DB::raw("SUM(CASE WHEN error_type IS NULL OR error_type = '' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) as success_rate"),
                    DB::raw('AVG(fallback_count) as avg_fallback_count')
                )
                ->groupBy('backend_used')
                ->get();
        });

        return $this->success($data);
    }

    /**
     * DAG vs Agent 模式对比
     * GET /api/admin/metrics/dashboard/mode-comparison
     */
    public function modeComparison(Request $request): JsonResponse
    {
        $days = $this->getDays($request);
        $cacheKey = "admin:metrics:mode-comparison:{$days}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($days) {
            return ChatSession::where('created_at', '>=', now()->subDays($days))
                ->select(
                    'execution_mode',
                    DB::raw('COUNT(*) as total_calls'),
                    DB::raw('AVG(ttfb_ms) as avg_ttfb_ms'),
                    DB::raw('AVG(duration_ms) as avg_duration_ms'),
                    DB::raw('AVG(tokens_per_sec) as avg_tokens_per_sec'),
                    DB::raw('SUM(credits_consumed) as total_credits'),
                    DB::raw('AVG(overall_score) as avg_quality_score'),
                    DB::raw("SUM(CASE WHEN error_type IS NULL OR error_type = '' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) as success_rate")
                )
                ->groupBy('execution_mode')
                ->get();
        });

        return $this->success($data);
    }

    /**
     * 工具使用热力图
     * GET /api/admin/metrics/dashboard/tool-usage
     */
    public function toolUsage(Request $request): JsonResponse
    {
        $days = $this->getDays($request);
        $cacheKey = "admin:metrics:tool-usage:{$days}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($days) {
            $sessions = ChatSession::where('created_at', '>=', now()->subDays($days))
                ->whereNotNull('tools_used')
                ->select('tools_used')
                ->get();

            // 统计各工具调用频次
            $toolCounts = [];
            $comboCounts = [];

            foreach ($sessions as $session) {
                $tools = $session->tools_used;
                if (!is_array($tools) || empty($tools)) {
                    continue;
                }

                foreach ($tools as $tool) {
                    $toolName = is_string($tool) ? $tool : ($tool['name'] ?? 'unknown');
                    $toolCounts[$toolName] = ($toolCounts[$toolName] ?? 0) + 1;
                }

                // 工具组合（排序后拼接，去重）
                $toolNames = array_map(fn($t) => is_string($t) ? $t : ($t['name'] ?? 'unknown'), $tools);
                sort($toolNames);
                $combo = implode(' + ', $toolNames);
                $comboCounts[$combo] = ($comboCounts[$combo] ?? 0) + 1;
            }

            arsort($toolCounts);
            arsort($comboCounts);

            return [
                'tools' => collect($toolCounts)->map(fn($count, $name) => ['name' => $name, 'count' => $count])->values()->take(20),
                'combos' => collect($comboCounts)->map(fn($count, $combo) => ['combo' => $combo, 'count' => $count])->values()->take(10),
                'total_sessions' => $sessions->count(),
            ];
        });

        return $this->success($data);
    }

    /**
     * 用户消费排行
     * GET /api/admin/metrics/dashboard/user-consumption
     */
    public function userConsumption(Request $request): JsonResponse
    {
        $days = $this->getDays($request);
        $cacheKey = "admin:metrics:user-consumption:{$days}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($days) {
            $since = now()->subDays($days);

            // Top 用户排行
            $topUsers = ChatSession::where('chat_sessions.created_at', '>=', $since)
                ->whereNotNull('chat_sessions.user_id')
                ->join('users', 'chat_sessions.user_id', '=', 'users.id')
                ->select(
                    'users.id',
                    'users.name',
                    'users.membership_tier',
                    DB::raw('COUNT(*) as total_queries'),
                    DB::raw('SUM(chat_sessions.credits_consumed) as total_credits'),
                    DB::raw('SUM(chat_sessions.input_tokens + chat_sessions.output_tokens) as total_tokens'),
                    DB::raw('AVG(chat_sessions.overall_score) as avg_quality')
                )
                ->groupBy('users.id', 'users.name', 'users.membership_tier')
                ->orderByDesc('total_credits')
                ->limit(20)
                ->get();

            // 按天消费趋势
            $trend = ChatSession::where('created_at', '>=', $since)
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(credits_consumed) as total_credits'),
                    DB::raw('COUNT(DISTINCT user_id) as active_users'),
                    DB::raw('COUNT(*) as total_queries')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get();

            return [
                'top_users' => $topUsers,
                'trend' => $trend,
            ];
        });

        return $this->success($data);
    }

    /**
     * 质量趋势
     * GET /api/admin/metrics/dashboard/quality-trend
     */
    public function qualityTrend(Request $request): JsonResponse
    {
        $days = $this->getDays($request);
        $cacheKey = "admin:metrics:quality-trend:{$days}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($days) {
            $since = now()->subDays($days);

            // 三轨评分按天趋势
            $trend = ChatSession::where('created_at', '>=', $since)
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('AVG(overall_score) as avg_overall_score'),
                    DB::raw('AVG((ux_clarity + ux_practicality + ux_detail + ux_friendliness + ux_satisfaction) / 5.0) as avg_ux_score'),
                    DB::raw('AVG((profile_utilization_rate + goal_alignment + uniqueness + dynamic_adjustment) / 4.0) as avg_personalization_pct'),
                    DB::raw('SUM(CASE WHEN fewshot_eligible = 1 THEN 1 ELSE 0 END) as fewshot_count'),
                    DB::raw('COUNT(*) as total_sessions')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get();

            // 个性化等级分布
            $gradeDistribution = ChatSession::where('created_at', '>=', $since)
                ->whereNotNull('personalization_grade')
                ->select(
                    'personalization_grade',
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('personalization_grade')
                ->get();

            return [
                'trend' => $trend,
                'grade_distribution' => $gradeDistribution,
            ];
        });

        return $this->success($data);
    }

    /**
     * 清除仪表盘缓存
     *
     * 在 InternalCreditController 写入性能字段后调用。
     */
    public static function clearDashboardCache(): void
    {
        $types = ['system-overview', 'model-comparison', 'mode-comparison', 'tool-usage', 'user-consumption', 'quality-trend'];
        $dayOptions = [7, 30, 90];

        foreach ($types as $type) {
            foreach ($dayOptions as $days) {
                Cache::forget("admin:metrics:{$type}:{$days}");
            }
        }
    }

    /**
     * 获取 days 参数（默认7，最大90）
     */
    private function getDays(Request $request): int
    {
        $days = (int) $request->input('days', 7);
        return min(max($days, 1), 90);
    }
}
