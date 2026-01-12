<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 3.6, 4.4
 */
class CreditController extends Controller
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
                return response()->json([
                    'code' => 404,
                    'msg' => '用户不存在',
                    'data' => null
                ], 404);
            }
            
            $dagCredits = $validated['dag_credits'] ?? 0;
            $agentCredits = $validated['agent_credits'] ?? 0;
            
            // 至少需要添加一种额度
            if ($dagCredits === 0 && $agentCredits === 0) {
                return response()->json([
                    'code' => 422,
                    'msg' => '至少需要添加一种额度',
                    'data' => null
                ], 422);
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
                return response()->json([
                    'code' => 400,
                    'msg' => $result['message'],
                    'data' => $result
                ], 400);
            }
            
            Log::info('管理员添加用户额度', [
                'admin_id' => $adminId,
                'user_id' => $id,
                'dag_credits' => $dagCredits,
                'agent_credits' => $agentCredits,
                'reason' => $validated['reason'],
            ]);
            
            return response()->json([
                'code' => 200,
                'msg' => '添加成功',
                'data' => $result
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'msg' => '参数验证失败',
                'data' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('添加用户额度失败', [
                'error' => $e->getMessage(),
                'user_id' => $id,
                'admin_id' => auth()->id(),
            ]);
            
            return response()->json([
                'code' => 500,
                'msg' => '添加额度失败',
                'data' => null
            ], 500);
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
                return response()->json([
                    'code' => 422,
                    'msg' => '至少需要添加一种额度',
                    'data' => null
                ], 422);
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
            
            return response()->json([
                'code' => 200,
                'msg' => "批量添加完成，成功{$result['success_count']}个，失败{$result['fail_count']}个",
                'data' => $result
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'msg' => '参数验证失败',
                'data' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('批量添加用户额度失败', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id(),
            ]);
            
            return response()->json([
                'code' => 500,
                'msg' => '批量添加额度失败',
                'data' => null
            ], 500);
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
            
            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取系统额度统计失败', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'code' => 500,
                'msg' => '获取统计失败',
                'data' => null
            ], 500);
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
                return response()->json([
                    'code' => 404,
                    'msg' => '用户不存在',
                    'data' => null
                ], 404);
            }
            
            $limit = $validated['limit'] ?? 50;
            
            $currentCredits = $this->creditService->getCredits($id);
            $logs = $this->creditService->getCreditHistory($id, $limit);
            
            return response()->json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => [
                    'user_id' => $id,
                    'user_name' => $user->name,
                    'current_credits' => $currentCredits,
                    'logs' => $logs,
                ]
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'msg' => '参数验证失败',
                'data' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('获取用户额度历史失败', [
                'error' => $e->getMessage(),
                'user_id' => $id,
            ]);
            
            return response()->json([
                'code' => 500,
                'msg' => '获取历史失败',
                'data' => null
            ], 500);
        }
    }
}
