<?php

namespace App\Http\Controllers\Internal;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * InternalUsageController - 内部用量检查端点
 *
 * 供DAML-RAG服务通过X-Internal-Token调用，
 * 替代需要jwt.auth的 /api/usage/check。
 *
 * API端点：
 * - POST /api/internal/usage/check - 检查用户是否可执行查询
 *
 * @version v1.0.0
 * @date 2026-03-01
 * @author 薛小川
 * @requirements 4.3
 */
class InternalUsageController extends BaseController
{
    protected UsageService $usageService;

    public function __construct(UsageService $usageService)
    {
        $this->usageService = $usageService;
    }

    /**
     * 检查用户是否可以执行查询（内部API）
     * POST /api/internal/usage/check
     *
     * @param Request $request
     * @return JsonResponse
     *
     * 请求格式：
     * {
     *     "user_id": 123,
     *     "mode": "dag"
     * }
     *
     * 响应格式：
     * {
     *     "code": 200,
     *     "msg": "检查完成",
     *     "data": {
     *         "can_execute": true,
     *         "dag_used": 3,
     *         "dag_limit": 10,
     *         "dag_remaining": 7,
     *         "agent_used": 1,
     *         "agent_limit": 3,
     *         "agent_remaining": 2,
     *         "dag_credits": 5,
     *         "agent_credits": 2,
     *         "message": "可以执行查询（剩余7次）"
     *     }
     * }
     */
    public function check(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|min:1',
                'mode' => 'required|string|in:dag,agent',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败', 400, $validator->errors()->toArray());
            }

            $validated = $validator->validated();
            $userId = $validated['user_id'];
            $mode = $validated['mode'];

            // 检查是否可执行
            $checkResult = $this->usageService->canExecuteQuery($userId, $mode);

            // 获取完整用量数据
            $todayUsage = $this->usageService->getTodayUsage($userId);

            $responseData = [
                'can_execute' => $checkResult['allowed'],
                'dag_used' => $todayUsage['dag_used'] ?? 0,
                'dag_limit' => $todayUsage['dag_limit'] ?? -1,
                'dag_remaining' => $todayUsage['dag_remaining'] ?? -1,
                'agent_used' => $todayUsage['agent_used'] ?? 0,
                'agent_limit' => $todayUsage['agent_limit'] ?? -1,
                'agent_remaining' => $todayUsage['agent_remaining'] ?? -1,
                'dag_credits' => $todayUsage['dag_credits'] ?? 0,
                'agent_credits' => $todayUsage['agent_credits'] ?? 0,
                'message' => $checkResult['message'] ?? '',
            ];

            Log::debug('内部用量检查', [
                'user_id' => $userId,
                'mode' => $mode,
                'can_execute' => $responseData['can_execute'],
            ]);

            return $this->success($responseData, '检查完成');

        } catch (\Exception $e) {
            Log::error('内部用量检查异常', [
                'user_id' => $request->input('user_id'),
                'mode' => $request->input('mode'),
                'error' => $e->getMessage(),
            ]);

            return $this->fail('用量检查失败: ' . $e->getMessage(), 500);
        }
    }
}
