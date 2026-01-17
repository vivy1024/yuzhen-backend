<?php

namespace App\Http\Controllers\Api\Admin;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Services\CreditService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * AdminCreditController - 管理员额度管理API控制器
 * 
 * 提供管理员对用户额度的管理接口
 * 
 * API端点：
 * - POST /api/admin/users/{id}/credits       - 为单个用户添加额度
 * - POST /api/admin/users/credits/batch      - 批量添加额度
 * - GET  /api/admin/credits/stats            - 获取系统额度统计
 * - GET  /api/admin/users/{id}/credits/logs  - 获取用户额度变更历史
 * 
 * 所有接口需要管理员权限
 * 
 * @version v1.1.0
 * @date 2026-01-17
 * @author 薛小川
 * @requirements 3.6, 4.4
 */
class CreditController extends BaseController
{
    /**
     * @var CreditService
     */
    protected $creditService;

    /**
     * 构造函数
     */
    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    /**
     * 为单个用户添加额度
     * POST /api/admin/users/{id}/credits
     * 
     * @param Request $request
     * @param int $id 用户ID
     * @return JsonResponse
     * 
     * 请求参数：
     * {
     *     "dag_credits": 10,      // DAG额度增加量
     *     "agent_credits": 5,     // Agent额度增加量
     *     "reason": "打赏奖励"    // 添加原因（必填）
     * }
     * 
     * 响应格式：
     * {
     *     "code": 200,
     *     "msg": "添加成功",
     *     "data": {
     *         "success": true,
     *         "dag_credits": 15,
     *         "agent_credits": 7,
     *         "message": "成功添加额度：DAG +10，Agent +5"
     *     }
     * }
     */
    public function addCredits(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'dag_credits' => 'nullable|integer|min:0|max:10000',
                'agent_credits' => 'nullable|integer|min:0|max:10000',
                'reason' => 'required|string|max:255',
            ]);
            
            // 验证用户是否存在
            $user = User::find($id);
            if (!$user) {
                return $this->fail('用户不存在', 404);
            }
            
            $dagCredits = $validated['dag_credits'] ?? 0;
            $agentCredits = $validated['agent_credits'] ?? 0;
            
            // 至少需要添加一种额度
            if ($dagCredits === 0 && $agentCredits === 0) {
                return $this->fail('至少需要添加一种额度', 422);
            }
            
            $adminId = auth()->id();
            
            $result = $this->creditService->addCredits(
                $id,
                $dagCredits,
                $agentCredits,
                $validated['reason'],
                $adminId
            );
            
            if (!$result['success']) {
                return $this->fail($result['message'], 400, $result);
            }
            
            Log::info('管理员添加用户额度', [
                'admin_id' => $adminId,
                'user_id' => $id,
                'dag_credits' => $dagCredits,
                'agent_credits' => $agentCredits,
                'reason' => $validated['reason'],
            ]);
            
            return $this->success($result, '添加成功');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('参数验证失败', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, '添加用户额度');
        }
    }

    /**
     * 批量添加额度
     * POST /api/admin/users/credits/batch
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * 请求参数：
     * {
     *     "user_ids": [1, 2, 3],   // 用户ID数组
     *     "dag_credits": 10,       // DAG额度增加量
     *     "agent_credits": 5,      // Agent额度增加量
     *     "reason": "活动奖励"     // 添加原因（必填）
     * }
     * 
     * 响应格式：
     * {
     *     "code": 200,
     *     "msg": "批量添加完成",
     *     "data": {
     *         "success_count": 3,
     *         "fail_count": 0,
     *         "failed_users": []
     *     }
     * }
     */
    public function batchAddCredits(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_ids' => 'required|array|min:1|max:100',
                'user_ids.*' => 'required|integer|exists:users,id',
                'dag_credits' => 'nullable|integer|min:0|max:10000',
                'agent_credits' => 'nullable|integer|min:0|max:10000',
                'reason' => 'required|string|max:255',
            ]);
            
            $dagCredits = $validated['dag_credits'] ?? 0;
            $agentCredits = $validated['agent_credits'] ?? 0;
            
            // 至少需要添加一种额度
            if ($dagCredits === 0 && $agentCredits === 0) {
                return $this->fail('至少需要添加一种额度', 422);
            }
            
            $adminId = auth()->id();
            
            $result = $this->creditService->batchAddCredits(
                $validated['user_ids'],
                $dagCredits,
                $agentCredits,
                $validated['reason'],
                $adminId
            );
            
            Log::info('管理员批量添加用户额度', [
                'admin_id' => $adminId,
                'user_count' => count($validated['user_ids']),
                'success_count' => $result['success_count'],
                'fail_count' => $result['fail_count'],
                'dag_credits' => $dagCredits,
                'agent_credits' => $agentCredits,
                'reason' => $validated['reason'],
            ]);
            
            return $this->success($result, "批量添加完成，成功{$result['success_count']}个，失败{$result['fail_count']}个");
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('参数验证失败', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, '批量添加用户额度');
        }
    }

    /**
     * 获取系统额度统计
     * GET /api/admin/credits/stats
     * 
     * @return JsonResponse
     * 
     * 响应格式：
     * {
     *     "code": 200,
     *     "msg": "获取成功",
     *     "data": {
     *         "total_dag_credits": 1000,
     *         "total_agent_credits": 500,
     *         "users_with_credits": 50
     *     }
     * }
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->creditService->getSystemCreditStats();
            
            return $this->success($stats, '获取成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取系统额度统计');
        }
    }

    /**
     * 获取用户额度变更历史
     * GET /api/admin/users/{id}/credits/logs
     * 
     * @param Request $request
     * @param int $id 用户ID
     * @return JsonResponse
     * 
     * 请求参数：
     * - limit: 返回条数（默认50）
     * 
     * 响应格式：
     * {
     *     "code": 200,
     *     "msg": "获取成功",
     *     "data": {
     *         "user_id": 1,
     *         "current_credits": {
     *             "dag_credits": 15,
     *             "agent_credits": 7
     *         },
     *         "logs": [
     *             {
     *                 "id": 1,
     *                 "dag_amount": 10,
     *                 "agent_amount": 5,
     *                 "reason": "打赏奖励",
     *                 "admin_name": "管理员",
     *                 "type": "添加",
     *                 "created_at": "2026-01-11 10:00:00"
     *             }
     *         ]
     *     }
     * }
     */
    public function logs(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'limit' => 'nullable|integer|min:1|max:200',
            ]);
            
            // 验证用户是否存在
            $user = User::find($id);
            if (!$user) {
                return $this->fail('用户不存在', 404);
            }
            
            $limit = $validated['limit'] ?? 50;
            
            $currentCredits = $this->creditService->getCredits($id);
            $logs = $this->creditService->getCreditHistory($id, $limit);
            
            return $this->success([
                'user_id' => $id,
                'user_name' => $user->name,
                'current_credits' => $currentCredits,
                'logs' => $logs,
            ], '获取成功');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('参数验证失败', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, '获取用户额度历史');
        }
    }
}
