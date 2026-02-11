<?php

namespace App\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Services\SecurityAuditLogger;

/**
 * Internal API Authentication Middleware
 * 
 * 用于验证MCP/CrewAI等内部服务的API访问
 * 
 * 安全加固：
 * - 移除硬编码默认Token
 * - 从环境变量读取Token，未配置时返回503
 * - 验证Token长度至少32字符
 * - 使用hash_equals进行时间安全比较
 * 
 * @see Requirements 3.1, 3.2, 3.3, 3.4
 */
class InternalApiAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 从环境变量读取Token，不使用默认值
        $expectedToken = config('app.internal_api_token');
        
        // 如果未配置Token，拒绝所有请求
        if (empty($expectedToken)) {
            Log::channel('security')->warning('Internal API token not configured', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Service unavailable',
                'code' => 503
            ], 503);
        }
        
        // 验证Token长度（至少32字符）
        if (strlen($expectedToken) < 32) {
            Log::channel('security')->error('Internal API token too short', [
                'length' => strlen($expectedToken),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Service configuration error',
                'code' => 500
            ], 500);
        }
        
        $internalToken = $request->header('X-Internal-Token');
        
        // 使用hash_equals进行时间安全比较，防止时序攻击
        if (!hash_equals($expectedToken, $internalToken ?? '')) {
            // 集成SecurityAuditLogger记录认证失败（Requirements 11.1）
            SecurityAuditLogger::logAuthFailure(
                'internal_api',
                'X-Internal-Token',
                $request->ip(),
                [
                    'path' => $request->path(),
                    'method' => $request->method(),
                ]
            );
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'code' => 401
            ], 401);
        }
        
        return $next($request);
    }
}












