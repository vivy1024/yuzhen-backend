<?php

namespace App\Modules\Auth\Middleware;

use App\Modules\Auth\Services\JwtService;
use App\Modules\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * JWT Authenticate Middleware
 * 
 * JWT认证中间件
 */
class JwtAuthenticate
{
    protected JwtService $jwtService;
    
    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function handle(Request $request, Closure $next)
    {
        // 获取Token
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'code' => 401,
                'msg' => '未提供认证令牌',
                'data' => null
            ], 401);
        }
        
        // 验证Token
        $payload = $this->jwtService->verifyToken($token);
        
        if (!$payload) {
            return response()->json([
                'code' => 401,
                'msg' => '认证令牌无效或已过期',
                'data' => null
            ], 401);
        }
        
        // 查找用户
        $user = User::find($payload['user_id']);
        
        if (!$user) {
            return response()->json([
                'code' => 401,
                'msg' => '用户不存在',
                'data' => null
            ], 401);
        }
        
        // ✅ 设置当前用户到Laravel Auth系统
        // 方法1: 设置到request（用于request()->user()）
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        // 方法2: 设置到Auth门面（用于auth()->user()和auth()->id()）
        auth()->setUser($user);
        
        return $next($request);
    }
}

