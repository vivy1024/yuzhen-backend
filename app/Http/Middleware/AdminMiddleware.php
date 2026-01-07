<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理员中间件
 * 
 * 检查用户是否为管理员
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'code' => 401,
                'msg' => '请先登录',
                'data' => null
            ], 401);
        }
        
        if (!$user->isAdmin()) {
            return response()->json([
                'code' => 403,
                'msg' => '需要管理员权限',
                'data' => null
            ], 403);
        }
        
        return $next($request);
    }
}
