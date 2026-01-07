<?php

namespace App\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Internal API Authentication Middleware
 * 
 * 用于验证MCP/CrewAI等内部服务的API访问
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
        $internalToken = $request->header('X-Internal-Token');
        $expectedToken = config('app.internal_api_token', 'crewai-internal-secret-2025');
        
        if ($internalToken !== $expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid internal API token',
                'code' => 401
            ], 401);
        }
        
        return $next($request);
    }
}












