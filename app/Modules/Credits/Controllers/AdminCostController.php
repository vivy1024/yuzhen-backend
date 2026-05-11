<?php

namespace App\Modules\Credits\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * AdminCostController - 管理员成本监控控制器
 *
 * API 端点：
 * - GET /api/admin/costs/overview — 成本总览
 * - GET /api/admin/costs/by-model — 按模型分布
 * - GET /api/admin/costs/by-user — 按用户排行
 * - GET /api/admin/costs/trend — 每日趋势
 * - GET /api/admin/costs/logs — 请求日志列表（分页）
 *
 * 中间件: jwt.auth + admin
 *
 * @version v1.0.0
 */
class AdminCostController extends BaseController
{
    /**
     * 成本总览
     * GET /api/admin/costs/overview?period=today|week|month
     *
     * 返回：total_cost_usd, total_credits_consumed, total_requests, avg_duration_ms
     */
    public function overview(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'period' => 'nullable|string|in:today,week,month',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败', 422, $validator->errors()->toArray());
            }

            $period = $request->input('period', 'today');
            $dateRange = $this->getDateRange($period);

            $stats = DB::table('ai_request_logs')
                ->whereBetween('created_at', $dateRange)
                ->selectRaw('
                    COALESCE(SUM(real_cost_usd), 0) as total_cost_usd,
                    COALESCE(SUM(credits_cost), 0) as total_credits_consumed,
                    COUNT(*) as total_requests,
                    COALESCE(AVG(duration_ms), 0) as avg_duration_ms,
                    COALESCE(SUM(input_tokens), 0) as total_input_tokens,
                    COALESCE(SUM(output_tokens), 0) as total_output_tokens,
                    SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN status = "error" THEN 1 ELSE 0 END) as error_count
                ')
                ->first();

            $successRate = $stats->total_requests > 0
                ? round(($stats->success_count / $stats->total_requests) * 100, 2)
                : 0;

            return $this->success([
                'period' => $period,
                'total_cost_usd' => number_format((float) $stats->total_cost_usd, 8, '.', ''),
                'total_credits_consumed' => number_format((float) $stats->total_credits_consumed, 6, '.', ''),
                'total_requests' => (int) $stats->total_requests,
                'avg_duration_ms' => round((float) $stats->avg_duration_ms),
                'total_input_tokens' => (int) $stats->total_input_tokens,
                'total_output_tokens' => (int) $stats->total_output_tokens,
                'success_rate' => $successRate,
                'error_count' => (int) $stats->error_count,
            ], '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取成本总览');
        }
    }

    /**
     * 按模型分布
     * GET /api/admin/costs/by-model?period=today|week|month
     *
     * 返回：[{ model, requests, input_tokens, output_tokens, cost_usd, credits }]
     */
    public function byModel(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'period' => 'nullable|string|in:today,week,month',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败', 422, $validator->errors()->toArray());
            }

            $period = $request->input('period', 'today');
            $dateRange = $this->getDateRange($period);

            $data = DB::table('ai_request_logs')
                ->whereBetween('created_at', $dateRange)
                ->select([
                    'model',
                    DB::raw('COUNT(*) as requests'),
                    DB::raw('COALESCE(SUM(input_tokens), 0) as input_tokens'),
                    DB::raw('COALESCE(SUM(output_tokens), 0) as output_tokens'),
                    DB::raw('COALESCE(SUM(real_cost_usd), 0) as cost_usd'),
                    DB::raw('COALESCE(SUM(credits_cost), 0) as credits'),
                    DB::raw('COALESCE(AVG(duration_ms), 0) as avg_duration_ms'),
                ])
                ->groupBy('model')
                ->orderByDesc('requests')
                ->get();

            return $this->success([
                'period' => $period,
                'items' => $data,
            ], '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取按模型成本分布');
        }
    }

    /**
     * 按用户排行
     * GET /api/admin/costs/by-user?period=today|week|month&limit=10
     *
     * 返回：[{ user_id, name, requests, cost_usd, credits }]
     */
    public function byUser(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'period' => 'nullable|string|in:today,week,month',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败', 422, $validator->errors()->toArray());
            }

            $period = $request->input('period', 'today');
            $limit = $request->input('limit', 10);
            $dateRange = $this->getDateRange($period);

            $data = DB::table('ai_request_logs as arl')
                ->join('users', 'users.id', '=', 'arl.user_id')
                ->whereBetween('arl.created_at', $dateRange)
                ->select([
                    'arl.user_id',
                    'users.name',
                    'users.email',
                    DB::raw('COUNT(*) as requests'),
                    DB::raw('COALESCE(SUM(arl.input_tokens), 0) as input_tokens'),
                    DB::raw('COALESCE(SUM(arl.output_tokens), 0) as output_tokens'),
                    DB::raw('COALESCE(SUM(arl.real_cost_usd), 0) as cost_usd'),
                    DB::raw('COALESCE(SUM(arl.credits_cost), 0) as credits'),
                ])
                ->groupBy('arl.user_id', 'users.name', 'users.email')
                ->orderByDesc('credits')
                ->limit($limit)
                ->get();

            return $this->success([
                'period' => $period,
                'limit' => $limit,
                'items' => $data,
            ], '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取按用户成本排行');
        }
    }

    /**
     * 每日趋势
     * GET /api/admin/costs/trend?days=7
     *
     * 返回：[{ date, requests, cost_usd, credits, input_tokens, output_tokens }]
     */
    public function trend(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'days' => 'nullable|integer|min:1|max:90',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败', 422, $validator->errors()->toArray());
            }

            $days = $request->input('days', 7);
            $startDate = now()->subDays($days)->startOfDay();

            $data = DB::table('ai_request_logs')
                ->where('created_at', '>=', $startDate)
                ->select([
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as requests'),
                    DB::raw('COALESCE(SUM(real_cost_usd), 0) as cost_usd'),
                    DB::raw('COALESCE(SUM(credits_cost), 0) as credits'),
                    DB::raw('COALESCE(SUM(input_tokens), 0) as input_tokens'),
                    DB::raw('COALESCE(SUM(output_tokens), 0) as output_tokens'),
                    DB::raw('COALESCE(AVG(duration_ms), 0) as avg_duration_ms'),
                    DB::raw('SUM(CASE WHEN status = "error" THEN 1 ELSE 0 END) as error_count'),
                ])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get();

            return $this->success([
                'days' => $days,
                'items' => $data,
            ], '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取每日趋势');
        }
    }

    /**
     * 请求日志列表（分页）
     * GET /api/admin/costs/logs?page=&model=&user_id=&status=
     */
    public function logs(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'model' => 'nullable|string|max:100',
                'user_id' => 'nullable|integer|min:1',
                'status' => 'nullable|string|in:success,error,timeout',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败', 422, $validator->errors()->toArray());
            }

            $validated = $validator->validated();
            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 20;

            $query = DB::table('ai_request_logs as arl')
                ->leftJoin('users', 'users.id', '=', 'arl.user_id')
                ->select([
                    'arl.id',
                    'arl.user_id',
                    'users.name as user_name',
                    'arl.session_id',
                    'arl.model',
                    'arl.provider',
                    'arl.input_tokens',
                    'arl.output_tokens',
                    'arl.cache_creation_tokens',
                    'arl.cache_read_tokens',
                    'arl.real_cost_usd',
                    'arl.credits_cost',
                    'arl.duration_ms',
                    'arl.first_token_ms',
                    'arl.status',
                    'arl.error_message',
                    'arl.created_at',
                ]);

            // 筛选条件
            if (!empty($validated['model'])) {
                $query->where('arl.model', $validated['model']);
            }
            if (!empty($validated['user_id'])) {
                $query->where('arl.user_id', $validated['user_id']);
            }
            if (!empty($validated['status'])) {
                $query->where('arl.status', $validated['status']);
            }
            if (!empty($validated['date_from'])) {
                $query->where('arl.created_at', '>=', $validated['date_from'] . ' 00:00:00');
            }
            if (!empty($validated['date_to'])) {
                $query->where('arl.created_at', '<=', $validated['date_to'] . ' 23:59:59');
            }

            $total = $query->count();
            $items = $query
                ->orderByDesc('arl.created_at')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            return $this->success([
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ], '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取请求日志');
        }
    }

    /**
     * 根据 period 参数获取日期范围
     */
    private function getDateRange(string $period): array
    {
        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }
}
