<?php

namespace App\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Services\UsageService;

/**
 * QuotaCheck 中间件
 * 
 * 在转发请求到DAML-RAG前检查用户配额
 * 配额不足返回429状态码，响应体包含剩余配额信息
 * 
 * @version v1.0.0
 * @date 2026-02-11
 * @requirements 4.1, 4.2
 */
class QuotaCheck
{
    private UsageService $usageService;

    public function __construct(UsageService $usageService)
    {
        $this->usageService = $usageService;
    }

    /**
     * 在转发到DAML-RAG前检查用户配额
     * 
     * 从请求体中提取strategy字段判断查询模式（dag/agent）
     * 调用UsageService::canExecuteQuery检查配额
     * 配额不足返回429
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'msg' => '请先登录',
                'data' => null,
            ], 401);
        }

        // 从请求体提取查询模式：strategy字段映射到mode
        $strategy = strtolower($request->input('strategy', 'dag'));
        $mode = $this->resolveMode($strategy);

        try {
            $result = $this->usageService->canExecuteQuery($user->id, $mode);
        } catch (\Exception $e) {
            Log::error('[QuotaCheck] 配额检查异常', [
                'user_id' => $user->id,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);

            // fail-closed：异常时拒绝请求
            return response()->json([
                'code' => 500,
                'msg' => '配额检查服务异常，请稍后重试',
                'data' => null,
            ], 500);
        }

        if (!$result['allowed']) {
            Log::info('[QuotaCheck] 配额不足，拒绝请求', [
                'user_id' => $user->id,
                'mode' => $mode,
                'remaining' => $result['remaining'],
            ]);

            // 获取完整用量信息用于响应
            $todayUsage = $this->usageService->getTodayUsage($user->id);

            return response()->json([
                'code' => 429,
                'msg' => $result['message'],
                'data' => [
                    'allowed' => false,
                    'mode' => $mode,
                    'remaining' => $result['remaining'],
                    'daily_limit' => $todayUsage["{$mode}_limit"],
                    'used_count' => $todayUsage["{$mode}_used"],
                    'credits' => $todayUsage["{$mode}_credits"],
                ],
            ], 429);
        }

        return $next($request);
    }

    /**
     * 将strategy字段映射为UsageService的mode参数
     */
    private function resolveMode(string $strategy): string
    {
        return match ($strategy) {
            'agent', 'agent_query' => 'agent',
            default => 'dag',
        };
    }
}
