<?php

namespace App\Modules\Auth\Services;

use App\Modules\User\Models\User;
use App\Modules\Auth\Events\UserLoggedIn;
use App\Modules\Auth\Events\UserLoggedOut;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Auth Service
 * 
 * 认证服务层
 */
class AuthService
{
    protected JwtService $jwtService;
    
    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * 用户登录
     */
    public function login(string $identifier, string $password, string $ip): array
    {
        // 查找用户（支持用户名、邮箱、手机号）
        $user = $this->findUserByIdentifier($identifier);

        if (!$user) {
            Log::warning('登录失败：用户不存在', [
                'identifier' => $identifier,
                'ip' => $ip,
                'reason' => 'user_not_found',
            ]);
            throw new \Exception('用户不存在');
        }

        // 验证密码
        if (!Hash::check($password, $user->password)) {
            Log::warning('登录失败：密码错误', [
                'identifier' => $identifier,
                'user_id' => $user->id,
                'ip' => $ip,
                'reason' => 'wrong_password',
            ]);
            throw new \Exception('密码错误');
        }

        // 检查用户状态
        if ($user->status !== 1) {
            Log::warning('登录失败：用户已被禁用', [
                'identifier' => $identifier,
                'user_id' => $user->id,
                'ip' => $ip,
                'reason' => 'user_disabled',
            ]);
            throw new \Exception('用户已被禁用');
        }
        
        // 更新最后登录时间
        $user->updateLastLogin($ip);
        
        // 生成Token
        $token = $this->jwtService->generateToken($user);
        $refreshToken = $this->jwtService->generateRefreshToken($user);
        
        // 触发登录事件
        Event::dispatch(new UserLoggedIn($user->toArray(), $ip));
        
        return [
            'user' => $user->toArray(),
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => config('auth.jwt_ttl', 3600),
        ];
    }

    /**
     * 用户注册
     */
    public function register(array $data): array
    {
        // 将 nickname 映射到 name 字段（前端使用 nickname，数据库使用 name）
        if (isset($data['nickname']) && !isset($data['name'])) {
            $data['name'] = $data['nickname'];
            unset($data['nickname']);
        }
        
        // 检查用户名是否已存在（使用name字段）
        if (isset($data['username']) && User::where('name', $data['username'])->exists()) {
            throw new \Exception('用户名已存在');
        }
        
        // 如果使用name字段注册，也要检查
        if (isset($data['name']) && User::where('name', $data['name'])->exists()) {
            throw new \Exception('用户名已存在');
        }
        
        // 检查邮箱是否已存在
        if (User::where('email', $data['email'])->exists()) {
            throw new \Exception('邮箱已被注册');
        }
        
        // 移除不需要的字段
        unset($data['password_confirmation']);
        unset($data['agree_terms']);
        
        // 加密密码
        $data['password'] = Hash::make($data['password']);
        $data['role'] = 'user'; // 默认角色
        $data['status'] = 1; // 默认激活
        
        // 创建用户
        $user = User::create($data);
        
        // 创建用户档案
        $user->profile()->create([]);
        
        // 生成Token
        $token = $this->jwtService->generateToken($user);
        $refreshToken = $this->jwtService->generateRefreshToken($user);
        
        return [
            'user' => $user->toArray(),
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => config('auth.jwt_ttl', 3600),
        ];
    }

    /**
     * 用户登出（REQ-C3: JWT黑名单机制）
     */
    public function logout(User $user): bool
    {
        // 将当前Token加入Redis黑名单，TTL = Token剩余有效期
        $token = request()->bearerToken();
        if ($token) {
            $payload = $this->jwtService->verifyToken($token);
            if ($payload && isset($payload['exp'])) {
                $ttl = $payload['exp'] - time();
                if ($ttl > 0) {
                    Cache::put('jwt_blacklist:' . md5($token), true, $ttl);
                }
            }
        }

        // 触发登出事件
        Event::dispatch(new UserLoggedOut($user->toArray()));

        return true;
    }

    /**
     * 刷新Token
     */
    public function refreshToken(string $refreshToken): array
    {
        // 验证刷新令牌
        $payload = $this->jwtService->verifyRefreshToken($refreshToken);
        
        if (!$payload) {
            throw new \Exception('刷新令牌无效或已过期');
        }
        
        // 查找用户
        $user = User::find($payload['user_id']);
        
        if (!$user) {
            throw new \Exception('用户不存在');
        }
        
        // 生成新Token
        $newToken = $this->jwtService->generateToken($user);
        $newRefreshToken = $this->jwtService->generateRefreshToken($user);
        
        return [
            'access_token' => $newToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => config('auth.jwt_ttl', 3600),
        ];
    }

    /**
     * 根据标识符查找用户（用户名/邮箱/手机号）
     */
    private function findUserByIdentifier(string $identifier): ?User
    {
        return User::where('name', $identifier)
            ->orWhere('email', $identifier)
            ->orWhere('phone', $identifier)  // ✅ 已恢复：手机号唯一约束已添加
            ->first();
    }
}

