<?php

namespace App\Http\Controllers\Internal;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * UsageReportController - 用量上报接收端点
 * 
 * 接收DAML-RAG服务完成AI查询后的用量上报回调。
 * 通过 internal.api 中间件验证请求来源（X-Internal-Token或Internal JWT）。
 * 
 * API端点：
 * - POST /api/internal/usage/report - 接收用量上报
 * 
 * @version v1.0.0
 * @date 2026-01-18
 * @author 薛小川
 * @requirements 4.3
 */
class UsageReportController extends BaseController
{
    protected UsageService $usageService;

    public function __construct(UsageService $usageService)
    {
        $this->usageService = $usageService;
    }

    /**
     * 接收DAML-RAG的用量上报
     * POST /api/internal/usage/report
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * 请求格式：
     * {
     *     "user_id": 123,
     *     "mode": "dag",
     *     "session_id": "sess_abc123",
     *     "timestamp": "2026-01-18T10:30:00Z",
     *     "execution_time_ms": 1500
     * }
     * 
     * 响应格式（成功）：
     * {
     *     "code": 200,
     *     "msg": "success",
     *     "data": {
     *         "user_id": 123,
     *         "mode": "dag",
     *         "new_count": 5,
     *         "remaining": 5
     *     }
     * }
     */
    public function report(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|min:1',
                'mode' => 'required|string|in:dag,agent',
                'session_id' => 'required|string|max:255',
                'timestamp' => 'required|string',
                'execution_time_ms' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                Log::warning('用量上报请求参数无效', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all(),
                ]);

                return $this->fail('参数验证失败', 400, $validator->errors()->toArray());
            }

            $validated = $validator->validated();
            $userId = $validated['user_id'];
            $mode = $validated['mode'];

            $result = $this->usageService->incrementUsage($userId, $mode);

            if (!$result['success']) {
                Log::warning('用量上报记录失败', [
                    'user_id' => $userId,
                    'mode' => $mode,
                    'session_id' => $validated['session_id'],
                    'error' => $result['message'] ?? 'Unknown error',
                ]);

                return $this->fail($result['message'] ?? '用量记录失败', 500);
            }

            Log::info('用量上报成功', [
                'user_id' => $userId,
                'mode' => $mode,
                'session_id' => $validated['session_id'],
                'execution_time_ms' => $validated['execution_time_ms'],
                'new_count' => $result['new_count'] ?? 0,
                'remaining' => $result['remaining'] ?? 0,
            ]);

            return $this->success([
                'user_id' => $userId,
                'mode' => $mode,
                'new_count' => $result['new_count'] ?? 0,
                'remaining' => $result['remaining'] ?? 0,
            ], 'success');

        } catch (\Exception $e) {
            Log::error('用量上报异常', [
                'user_id' => $request->input('user_id'),
                'mode' => $request->input('mode'),
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->fail('用量上报失败: ' . $e->getMessage(), 500);
        }
    }
}
