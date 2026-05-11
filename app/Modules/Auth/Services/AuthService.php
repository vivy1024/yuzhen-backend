<?php

namespace App\Modules\Auth\Services;

use App\Modules\User\Models\User;
use App\Modules\Auth\Events\UserLoggedIn;
use App\Modules\Auth\Events\UserLoggedOut;
use App\Modules\Credits\Services\CreditService;
use App\Modules\Credits\Services\InviteService;
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
        // 检查是否被锁定
        $lockKey = "login_locked:{$identifier}";
        if (Cache::has($lockKey)) {
            $remainingSeconds = Cache::get($lockKey) - time();
            if ($remainingSeconds > 0) {
                throw new \Exception("登录失败次数过多，请{$remainingSeconds}秒后重试");
            }
            Cache::forget($lockKey);
        }

        // 查找用户（支持用户名、邮箱、手机号）
        $user = $this->findUserByIdentifier($identifier);

        if (!$user) {
            $this->recordLoginFailure($identifier);
            Log::warning('登录失败：用户不存在', [
                'identifier' => $identifier,
                'ip' => $ip,
                'reason' => 'user_not_found',
            ]);
            throw new \Exception('用户不存在');
        }

        // 验证密码
        if (!Hash::check($password, $user->password)) {
            $this->recordLoginFailure($identifier);
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

        // 登录成功，清除失败计数
        Cache::forget("login_fail_count:{$identifier}");
        
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
        
        // 提取邀请码（创建用户前取出，不写入 users 表的 create data）
        $inviteCode = $data['invite_code'] ?? null;
        unset($data['invite_code']);

        // 生成邀请码给新用户
        $inviteService = app(InviteService::class);
        $data['invite_code'] = $inviteService->generateInviteCode();

        // 创建用户
        $user = User::create($data);
        
        // 创建用户档案
        $user->profile()->create([]);
        
        // 创建积分账户
        app(CreditService::class)->getOrCreateAccount($user->id);

        // 处理邀请码（如果注册时带了邀请码）
        if ($inviteCode) {
            $inviteResult = $inviteService->processInvite($user->id, $inviteCode);
            if (!$inviteResult['success']) {
                Log::info('邀请码处理失败（不影响注册）', [
                    'user_id' => $user->id,
                    'invite_code' => $inviteCode,
                    'reason' => $inviteResult['message'],
                ]);
            }
        }
        
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

    /**
     * 记录登录失败，连续5次锁定15分钟
     */
    private function recordLoginFailure(string $identifier): void
    {
        $countKey = "login_fail_count:{$identifier}";
        $lockKey = "login_locked:{$identifier}";
        $maxAttempts = 5;
        $lockSeconds = 900; // 15分钟

        $count = (int) Cache::get($countKey, 0) + 1;
        Cache::put($countKey, $count, $lockSeconds);

        if ($count >= $maxAttempts) {
            Cache::put($lockKey, time() + $lockSeconds, $lockSeconds);
            Cache::forget($countKey);
            Log::warning('登录锁定：连续失败次数过多', [
                'identifier' => $identifier,
                'attempts' => $count,
                'lock_seconds' => $lockSeconds,
            ]);
        }
    }
}
