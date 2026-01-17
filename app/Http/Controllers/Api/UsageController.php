<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Services\UsageService;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * UsageController - 用量管理API控制器
 * 
 * 提供用户AI查询用量的查询、检查和增加接口
 * 
 * API端点：
 * - GET  /api/usage/today     - 获取今日用量
 * - GET  /api/usage/credits   - 获取额外额度
 * - POST /api/usage/check     - 检查是否可执行查询
 * - POST /api/usage/increment - 增加用量计数
 * 
 * @version v1.1.0
 * @date 2026-01-17
 * @author 薛小川
 * @requirements 4.1-4.6
 */
class UsageController extends BaseController
{
    /**
     * @var UsageService
     */
    protected $usageService;

    /**
     * @var CreditService
     */
    protected $creditService;

    /**
     * 构造函数
     */
    public function __construct(UsageService $usageService, CreditService $creditService)
    {
        $this->usageService = $usageService;
        $this->creditService = $creditService;
    }

    /**
     * 获取今日用量统计
     * GET /api/usage/today
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * 响应格式：
     * {
     *     "code": 200,
     *     "msg": "获取成功",
     *     "data": {
     *         "dag_used": 3,
     *         "dag_limit": 10,
     *         "dag_remaining": 7,
     *         "agent_used": 1,
     *         "agent_limit": 3,
     *         "agent_remaining": 2,
     *         "dag_credits": 5,
     *         "agent_credits": 2,
     *         "date": "2026-01-11"
     *     }
     * }
     */
    public function today(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->fail('请先登录', 401);
            }
            
            $usage = $this->usageService->getTodayUsage($user->id);
            
            // 检查是否有低用量警告
            $warning = $this->usageService->checkLowUsageWarning($user->id);
            
            return $this->success(array_merge($usage, [
                'has_warning' => $warning['has_warning'],
                'warnings' => $warning['warnings'],
            ]), '获取成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取今日用量');
        }
    }

    /**
     * 获取额外额度余额
     * GET /api/usage/credits
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * 响应格式：
     * {
     *     "code": 200,
     *     "msg": "获取成功",
     *     "data": {
     *         "dag_credits": 5,
     *         "agent_credits": 2,
     *         "total_credits": 7
     *     }
     * }
     */
    public function credits(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->fail('请先登录', 401);
            }
            
            $credits = $this->creditService->getCredits($user->id);
            
            return $this->success($credits, '获取成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取额度余额');
        }
    }

    /**
     * 检查是否可以执行查询
     * POST /api/usage/check
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * 请求参数：
     * {
     *     "mode": "dag" | "agent"
     * }
     * 
     * 响应格式：
     * {
     *     "code": 200,
     *     "msg": "检查完成",
     *     "data": {
     *         "allowed": true,
     *         "remaining": 7,
     *         "use_credits": false,
     *         "message": "可以执行查询（剩余7次）"
     *     }
     * }
     */
    public function check(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'mode' => 'required|string|in:dag,agent,DAG,Agent',
            ]);
            
            $user = $request->user();
            
            if (!$user) {
                return $this->fail('请先登录', 401);
            }
            
            $mode = strtolower($validated['mode']);
            $result = $this->usageService->canExecuteQuery($user->id, $mode);
            
            // 根据是否允许返回不同的状态码
            if ($result['allowed']) {
                return $this->success($result, '检查通过');
            } else {
                return $this->fail('用量已达上限', 429, $result, 429);
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('参数验证失败', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, '检查用量');
        }
    }

    /**
     * 增加用量计数
     * POST /api/usage/increment
     * 
     * 此接口通常由DAML-RAG服务在查询完成后调用
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * 请求参数：
     * {
     *     "mode": "dag" | "agent"
     * }
     * 
     * 响应格式：
     * {
     *     "code": 200,
     *     "msg": "计数成功",
     *     "data": {
     *         "success": true,
     *         "used_credits": false,
     *         "new_count": 4,
     *         "remaining": 6,
     *         "message": "查询成功（剩余6次）"
     *     }
     * }
     */
    public function increment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'mode' => 'required|string|in:dag,agent,DAG,Agent',
            ]);
            
            $user = $request->user();
            
            if (!$user) {
                return $this->fail('请先登录', 401);
            }
            
            $mode = strtolower($validated['mode']);
            $result = $this->usageService->incrementUsage($user->id, $mode);
            
            if (!$result['success']) {
                // 用量增加失败（可能是额度不足）
                return $this->fail($result['message'], 429, $result, 429);
            }
            
            Log::info('用量计数已增加', [
                'user_id' => $user->id,
                'mode' => $mode,
                'new_count' => $result['new_count'],
                'used_credits' => $result['used_credits'],
            ]);
            
            return $this->success($result, '计数成功');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('参数验证失败', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, '增加用量计数');
        }
    }

    /**
     * 获取用量历史统计
     * GET /api/usage/history
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * 请求参数：
     * - days: 统计天数（默认30天）
     * 
     * 响应格式：
     * {
     *     "code": 200,
     *     "msg": "获取成功",
     *     "data": {
     *         "period_days": 30,
     *         "total_dag_queries": 150,
     *         "total_agent_queries": 45,
     *         "avg_dag_per_day": 5.0,
     *         "avg_agent_per_day": 1.5,
     *         "daily_stats": [...]
     *     }
     * }
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'days' => 'nullable|integer|min:1|max:365',
            ]);
            
            $user = $request->user();
            
            if (!$user) {
                return $this->fail('请先登录', 401);
            }
            
            $days = $validated['days'] ?? 30;
            $history = $this->usageService->getUsageHistory($user->id, $days);
            
            return $this->success($history, '获取成功');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('参数验证失败', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, '获取用量历史');
        }
    }
}
