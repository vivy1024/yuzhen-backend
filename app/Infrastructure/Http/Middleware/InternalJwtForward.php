<?php

namespace App\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Services\InternalJwtService;

/**
 * InternalJwtForward 中间件
 * 
 * 在转发请求到DAML-RAG前，根据ENABLE_INTERNAL_JWT配置开关：
 * - 开启时：签发Internal JWT，附加到Authorization: Bearer头
 * - 关闭时：保持原有X-Internal-Token方式
 * - JWT签发失败时：降级到X-Internal-Token（容错）
 * 
 * @version v1.0.0
 * @date 2026-02-11
 * @requirements 2.1, 7.3
 */
class InternalJwtForward
{
    private InternalJwtService $jwtService;

    public function __construct(InternalJwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * 在转发请求到DAML-RAG前，签发Internal JWT并附加到请求头
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

        $enabled = config('auth.enable_internal_jwt', false);

        if ($enabled) {
            try {
                $token = $this->jwtService->issueToken($user->id);
                $request->headers->set('Authorization', "Bearer {$token}");

                Log::debug('[InternalJwtForward] JWT已附加到请求头', [
                    'user_id' => $user->id,
                ]);
            } catch (\Throwable $e) {
                // JWT签发失败，降级到X-Internal-Token
                Log::warning('[InternalJwtForward] JWT签发失败，降级到X-Internal-Token', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                // 关键：移除原始Authorization头（外部JWT），避免DAML-RAG误判
                $request->headers->remove('Authorization');
                $this->attachInternalToken($request);
            }
        } else {
            // 旧模式：移除Authorization头，仅用X-Internal-Token
            $request->headers->remove('Authorization');
            $this->attachInternalToken($request);
        }

        return $next($request);
    }

    /**
     * 附加X-Internal-Token到请求头（旧模式）
     */
    private function attachInternalToken(Request $request): void
    {
        $token = config('app.internal_api_token', '');

        if (!empty($token)) {
            $request->headers->set('X-Internal-Token', $token);
        }
    }
}
