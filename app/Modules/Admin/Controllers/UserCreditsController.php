<?php

namespace App\Modules\Admin\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Membership\Services\UsageTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 用户额外次数管理控制器
 * 
 * 管理员可以为用户添加额外的AI对话次数（打赏奖励）
 * 
 * @version v1.1.0
 * @date 2026-01-17 (修复API响应规范合规性)
 * @author 薛小川
 */
class UserCreditsController extends BaseController
{
    protected UsageTrackingService $usageService;
    
    public function __construct(UsageTrackingService $usageService)
    {
        $this->usageService = $usageService;
    }
    
    /**
     * 获取用户用量统计
     * 
     * GET /api/admin/users/{userId}/usage
     */
    public function getUserUsage(int $userId): JsonResponse
    {
        try {
            // 检查用户是否存在
            $user = DB::table('users')->find($userId);
            if (!$user) {
                return $this->fail('用户不存在', 404);
            }
            
            $stats = $this->usageService->getUserUsageStats($userId);
            
            return $this->success([
                'user_id' => $userId,
                'nickname' => $user->nickname,
                'email' => $user->email,
                'usage' => $stats
            ], '获取成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取用户用量');
        }
    }
    
    /**
     * 添加额外次数（打赏奖励）
     * 
     * POST /api/admin/users/{userId}/credits
     * 
     * Body:
     * {
     *   "dag_credits": 50,      // DAG额外次数
     *   "agent_credits": 10,    // Agent额外次数
     *   "reason": "打赏10元"    // 原因
     * }
     */
    public function addCredits(Request $request, int $userId): JsonResponse
    {
        try {
            // 验证请求
            $validated = $request->validate([
                'dag_credits' => 'nullable|integer|min:0|max:10000',
                'agent_credits' => 'nullable|integer|min:0|max:1000',
                'reason' => 'nullable|string|max:200'
            ]);
            
            // 检查用户是否存在
            $user = DB::table('users')->find($userId);
            if (!$user) {
                return $this->fail('用户不存在', 404);
            }
            
            $dagCredits = $validated['dag_credits'] ?? 0;
            $agentCredits = $validated['agent_credits'] ?? 0;
            $reason = $validated['reason'] ?? '管理员添加';
            
            if ($dagCredits == 0 && $agentCredits == 0) {
                return $this->fail('请至少添加一种类型的次数', 400);
            }
            
            // 添加额外次数
            $success = $this->usageService->addBonusCredits(
                $userId,
                $dagCredits,
                $agentCredits,
                $reason
            );
            
            if (!$success) {
                return $this->fail('添加失败', 500);
            }
            
            // 获取更新后的统计
            $stats = $this->usageService->getUserUsageStats($userId);
            
            Log::info("管理员添加额外次数", [
                'admin_id' => auth()->id(),
                'user_id' => $userId,
                'dag_credits' => $dagCredits,
                'agent_credits' => $agentCredits,
                'reason' => $reason
            ]);
            
            return $this->success([
                'user_id' => $userId,
                'added' => [
                    'dag_credits' => $dagCredits,
                    'agent_credits' => $agentCredits
                ],
                'current_usage' => $stats
            ], '添加成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '添加额外次数');
        }
    }
    
    /**
     * 批量添加额外次数
     * 
     * POST /api/admin/users/credits/batch
     * 
     * Body:
     * {
     *   "user_ids": [1, 2, 3],
     *   "dag_credits": 50,
     *   "agent_credits": 10,
     *   "reason": "活动奖励"
     * }
     */
    public function batchAddCredits(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_ids' => 'required|array|min:1|max:100',
                'user_ids.*' => 'integer|exists:users,id',
                'dag_credits' => 'nullable|integer|min:0|max:10000',
                'agent_credits' => 'nullable|integer|min:0|max:1000',
                'reason' => 'nullable|string|max:200'
            ]);
            
            $dagCredits = $validated['dag_credits'] ?? 0;
            $agentCredits = $validated['agent_credits'] ?? 0;
            $reason = $validated['reason'] ?? '批量添加';
            
            if ($dagCredits == 0 && $agentCredits == 0) {
                return $this->fail('请至少添加一种类型的次数', 400);
            }
            
            $successCount = 0;
            $failedUsers = [];
            
            foreach ($validated['user_ids'] as $userId) {
                $success = $this->usageService->addBonusCredits(
                    $userId,
                    $dagCredits,
                    $agentCredits,
                    $reason
                );
                
                if ($success) {
                    $successCount++;
                } else {
                    $failedUsers[] = $userId;
                }
            }
            
            Log::info("管理员批量添加额外次数", [
                'admin_id' => auth()->id(),
                'user_count' => count($validated['user_ids']),
                'success_count' => $successCount,
                'dag_credits' => $dagCredits,
                'agent_credits' => $agentCredits,
                'reason' => $reason
            ]);
            
            return $this->success([
                'total' => count($validated['user_ids']),
                'success' => $successCount,
                'failed' => count($failedUsers),
                'failed_users' => $failedUsers
            ], "成功为 {$successCount} 个用户添加额外次数");
            
        } catch (\Exception $e) {
            return $this->handleException($e, '批量添加额外次数');
        }
    }
    
    /**
     * 获取打赏奖励配置
     * 
     * GET /api/admin/credits/config
     */
    public function getCreditsConfig(): JsonResponse
    {
        try {
            $config = config('membership.donation_rewards', [
                '5' => 50,
                '10' => 120,
                '20' => 300,
                '50' => 1000
            ]);
            
            return $this->success([
                'donation_rewards' => $config,
                'default_limits' => [
                    'dag_per_day' => UsageTrackingService::DEFAULT_DAG_LIMIT,
                    'agent_per_day' => UsageTrackingService::DEFAULT_AGENT_LIMIT
                ]
            ], '获取成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取打赏奖励配置');
        }
    }
}
