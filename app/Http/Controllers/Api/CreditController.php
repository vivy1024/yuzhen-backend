<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Services\CreditService;
use App\Models\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * CreditController - 用户积分API控制器
 * 
 * 提供用户积分相关的API接口：
 * - GET /api/credits/balance - 获取积分余额
 * - GET /api/credits/history - 获取流水历史
 * - GET /api/credits/stats - 获取消耗统计
 * 
 * 所有接口需要用户认证
 * 
 * @version v1.0.0
 * @date 2026-02-05
 * @author 薛小川
 * @requirements 4.1, 3.4, 3.5
 */
class CreditController extends BaseController
{
    /**
     * @var CreditService
     */
    protected CreditService $creditService;

    /**
     * 构造函数
     * 
     * @param CreditService $creditService
     */
    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    /**
     * 获取用户积分余额
     * GET /api/credits/balance
     * 
     * @return JsonResponse
     * 
     * 响应格式：
     * {
     *     "code": 200,
     *     "msg": "success",
     *     "data": {
     *         "daily_quota": 50,
     *         "daily_consumed": 12,
     *         "remaining": 38,
     *         "total_consumed": 1250,
     *         "membership_tier": "warmheart",
     *         "is_mvp_phase": false,
     *         "low_balance_warning": false,
     *         "last_reset": "2026-01-18"
     *     }
     * }
     * 
     * @requirements 4.1, 4.2, 4.3, 4.4, 4.5
     */
    public function getBalance(): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return $this->fail('用户未登录', 401);
            }
            
            $balance = $this->creditService->getBalance($userId);
            
            Log::debug('用户查询积分余额', [
                'user_id' => $userId,
                'remaining' => $balance['remaining'],
                'daily_quota' => $balance['daily_quota'],
            ]);
            
            return $this->success($balance, '获取成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取积分余额');
        }
    }

    /**
     * 获取积分流水历史
     * GET /api/credits/history
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * 请求参数：
     * - page: 页码（默认1）
     * - per_page: 每页条数（默认10，最大50）
     * - mode: 筛选模式（可选，dag/agent）
     * - start_date: 开始日期（可选，格式：Y-m-d）
     * - end_date: 结束日期（可选，格式：Y-m-d）
     * 
     * 响应格式：
     * {
     *     "code": 200,
     *     "msg": "success",
     *     "data": {
     *         "transactions": [
     *             {
     *                 "id": 123,
     *                 "credits": 3,
     *                 "tokens": 2500,
     *                 "mode": "dag",
     *                 "mode_label": "DAG模式",
     *                 "template_name": "exercise_optimization",
     *                 "conversation_id": "conv_abc123",
     *                 "input_tokens": 800,
     *                 "output_tokens": 1700,
     *                 "description": null,
     *                 "created_at": "2026-01-18 10:30:00"
     *             }
     *         ],
     *         "pagination": {
     *             "current_page": 1,
     *             "total_pages": 5,
     *             "total_count": 48,
     *             "per_page": 10
     *         },
     *         "summary": {
     *             "today": 12,
     *             "this_week": 85,
     *             "this_month": 320
     *         }
     *     }
     * }
     * 
     * @requirements 3.4, 3.5
     */
    public function getHistory(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return $this->fail('用户未登录', 401);
            }
            
            // 验证请求参数
            $validated = $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:50',
                'mode' => 'nullable|string|in:dag,agent',
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            ]);
            
            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 10;
            
            // 构建查询
            $query = CreditTransaction::forUser($userId)->latest();
            
            // 按模式筛选
            if (!empty($validated['mode'])) {
                $query->byMode($validated['mode']);
            }
            
            // 按日期范围筛选
            if (!empty($validated['start_date'])) {
                $query->where('created_at', '>=', $validated['start_date'] . ' 00:00:00');
            }
            if (!empty($validated['end_date'])) {
                $query->where('created_at', '<=', $validated['end_date'] . ' 23:59:59');
            }
            
            // 分页查询
            $total = $query->count();
            $transactions = $query
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            // 格式化交易记录
            $formattedTransactions = $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'credits' => $transaction->credits,
                    'tokens' => $transaction->tokens,
                    'mode' => $transaction->mode,
                    'mode_label' => $transaction->getModeLabel(),
                    'template_name' => $transaction->template_name,
                    'conversation_id' => $transaction->conversation_id,
                    'input_tokens' => $transaction->input_tokens,
                    'output_tokens' => $transaction->output_tokens,
                    'description' => $transaction->description,
                    'created_at' => $transaction->getFormattedCreatedAt(),
                ];
            });
            
            // 获取消耗统计摘要
            $summary = CreditTransaction::getConsumptionSummary($userId);
            
            Log::debug('用户查询积分流水', [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ]);
            
            return $this->success([
                'transactions' => $formattedTransactions,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => (int) ceil($total / $perPage),
                    'total_count' => $total,
                    'per_page' => $perPage,
                ],
                'summary' => $summary,
            ], '获取成功');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('参数验证失败', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, '获取积分流水历史');
        }
    }

    /**
     * 获取积分消耗统计
     * GET /api/credits/stats
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * 请求参数：
     * - period: 统计周期（可选，today/week/month/all，默认all）
     * 
     * 响应格式：
     * {
     *     "code": 200,
     *     "msg": "success",
     *     "data": {
     *         "summary": {
     *             "today": 12,
     *             "this_week": 85,
     *             "this_month": 320
     *         },
     *         "by_mode": {
     *             "dag": {
     *                 "count": 45,
     *                 "credits": 180,
     *                 "tokens": 150000
     *             },
     *             "agent": {
     *                 "count": 15,
     *                 "credits": 140,
     *                 "tokens": 80000
     *             }
     *         },
     *         "by_template": [
     *             {
     *                 "template_name": "exercise_optimization",
     *                 "count": 20,
     *                 "credits": 80
     *             }
     *         ],
     *         "daily_trend": [
     *             {
     *                 "date": "2026-01-18",
     *                 "credits": 12,
     *                 "count": 5
     *             }
     *         ]
     *     }
     * }
     * 
     * @requirements 3.5
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return $this->fail('用户未登录', 401);
            }
            
            // 验证请求参数
            $validated = $request->validate([
                'period' => 'nullable|string|in:today,week,month,all',
            ]);
            
            $period = $validated['period'] ?? 'all';
            
            // 获取消耗统计摘要
            $summary = CreditTransaction::getConsumptionSummary($userId);
            
            // 构建基础查询
            $baseQuery = CreditTransaction::forUser($userId)->consumptions();
            
            // 根据周期筛选
            $periodQuery = clone $baseQuery;
            switch ($period) {
                case 'today':
                    $periodQuery->today();
                    break;
                case 'week':
                    $periodQuery->thisWeek();
                    break;
                case 'month':
                    $periodQuery->thisMonth();
                    break;
                // 'all' 不添加时间筛选
            }
            
            // 按模式统计
            $byMode = [];
            foreach ([CreditTransaction::MODE_DAG, CreditTransaction::MODE_AGENT] as $mode) {
                $modeQuery = (clone $periodQuery)->byMode($mode);
                $byMode[$mode] = [
                    'count' => $modeQuery->count(),
                    'credits' => (int) $modeQuery->sum('credits'),
                    'tokens' => (int) $modeQuery->sum('tokens'),
                ];
            }
            
            // 按模板统计（Top 10）
            $byTemplate = (clone $periodQuery)
                ->selectRaw('template_name, COUNT(*) as count, SUM(credits) as credits')
                ->whereNotNull('template_name')
                ->groupBy('template_name')
                ->orderByDesc('credits')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'template_name' => $item->template_name,
                        'count' => (int) $item->count,
                        'credits' => (int) $item->credits,
                    ];
                });
            
            // 每日趋势（最近7天）
            $dailyTrend = CreditTransaction::forUser($userId)
                ->consumptions()
                ->where('created_at', '>=', now()->subDays(7)->startOfDay())
                ->selectRaw('DATE(created_at) as date, SUM(credits) as credits, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'credits' => (int) $item->credits,
                        'count' => (int) $item->count,
                    ];
                });
            
            Log::debug('用户查询积分统计', [
                'user_id' => $userId,
                'period' => $period,
            ]);
            
            return $this->success([
                'summary' => $summary,
                'by_mode' => $byMode,
                'by_template' => $byTemplate,
                'daily_trend' => $dailyTrend,
            ], '获取成功');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('参数验证失败', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, '获取积分消耗统计');
        }
    }
}
