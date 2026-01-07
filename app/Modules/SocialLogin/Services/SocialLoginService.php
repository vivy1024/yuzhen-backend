<?php

namespace App\Modules\SocialLogin\Services;

use App\Modules\SocialLogin\Repositories\Interfaces\SocialAccountRepositoryInterface;
use App\Modules\User\Models\User;
use App\Modules\Auth\Services\JwtService;
use App\Modules\SocialLogin\Events\SocialLoginSuccessful;
use App\Modules\SocialLogin\Events\SocialAccountBound;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Social Login Service
 * 
 * 社交登录核心服务
 */
class SocialLoginService
{
    protected SocialAccountRepositoryInterface $repository;
    protected JwtService $jwtService;
    
    public function __construct(
        SocialAccountRepositoryInterface $repository,
        JwtService $jwtService
    ) {
        $this->repository = $repository;
        $this->jwtService = $jwtService;
    }

    /**
     * 处理社交登录回调
     * 
     * @param string $provider 平台名称（wechat/weibo/qq等）
     * @param array $socialUser 社交平台返回的用户信息
     * @return array 包含用户信息和token
     */
    public function handleCallback(string $provider, array $socialUser): array
    {
        // 1. 查找是否已绑定社交账号
        $socialAccount = $this->repository->findByProviderAndId(
            $provider,
            $socialUser['id']
        );
        
        if ($socialAccount) {
            // 已绑定，更新token和用户信息
            $this->repository->update($socialAccount['id'], [
                'provider_nickname' => $socialUser['nickname'] ?? null,
                'provider_avatar' => $socialUser['avatar'] ?? null,
                'provider_email' => $socialUser['email'] ?? null,
                'access_token' => $socialUser['access_token'] ?? null,
                'refresh_token' => $socialUser['refresh_token'] ?? null,
                'expires_in' => $socialUser['expires_in'] ?? null,
                'token_expires_at' => $this->calculateExpiresAt($socialUser['expires_in'] ?? null),
                'raw_data' => $socialUser['raw'] ?? [],
            ]);
            
            $user = User::find($socialAccount['user_id']);
        } else {
            // 未绑定，创建新用户并绑定
            $user = $this->createUserFromSocial($provider, $socialUser);
            
            $socialAccount = $this->repository->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $socialUser['id'],
                'provider_username' => $socialUser['username'] ?? null,
                'provider_nickname' => $socialUser['nickname'] ?? null,
                'provider_avatar' => $socialUser['avatar'] ?? null,
                'provider_email' => $socialUser['email'] ?? null,
                'access_token' => $socialUser['access_token'] ?? null,
                'refresh_token' => $socialUser['refresh_token'] ?? null,
                'expires_in' => $socialUser['expires_in'] ?? null,
                'token_expires_at' => $this->calculateExpiresAt($socialUser['expires_in'] ?? null),
                'raw_data' => $socialUser['raw'] ?? [],
            ]);
        }
        
        // 2. 生成JWT Token
        $accessToken = $this->jwtService->generateToken($user);
        $refreshToken = $this->jwtService->generateRefreshToken($user);
        
        // 3. 触发登录成功事件
        Event::dispatch(new SocialLoginSuccessful($user->toArray(), $provider));
        
        return [
            'user' => $user->toArray(),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ];
    }

    /**
     * 绑定社交账号到已登录用户
     */
    public function bindAccount(User $user, string $provider, array $socialUser): array
    {
        // 检查该社交账号是否已被其他用户绑定
        $existingAccount = $this->repository->findByProviderAndId(
            $provider,
            $socialUser['id']
        );
        
        if ($existingAccount && $existingAccount['user_id'] !== $user->id) {
            throw new \Exception('该社交账号已被其他用户绑定');
        }
        
        // 检查当前用户是否已绑定该平台
        $userAccount = $this->repository->findByUserAndProvider($user->id, $provider);
        
        if ($userAccount) {
            // 更新绑定信息
            $this->repository->update($userAccount['id'], [
                'provider_user_id' => $socialUser['id'],
                'provider_nickname' => $socialUser['nickname'] ?? null,
                'provider_avatar' => $socialUser['avatar'] ?? null,
                'provider_email' => $socialUser['email'] ?? null,
                'access_token' => $socialUser['access_token'] ?? null,
                'refresh_token' => $socialUser['refresh_token'] ?? null,
                'expires_in' => $socialUser['expires_in'] ?? null,
                'token_expires_at' => $this->calculateExpiresAt($socialUser['expires_in'] ?? null),
                'raw_data' => $socialUser['raw'] ?? [],
            ]);
            
            $socialAccount = $this->repository->findById($userAccount['id']);
        } else {
            // 创建新绑定
            $socialAccount = $this->repository->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $socialUser['id'],
                'provider_username' => $socialUser['username'] ?? null,
                'provider_nickname' => $socialUser['nickname'] ?? null,
                'provider_avatar' => $socialUser['avatar'] ?? null,
                'provider_email' => $socialUser['email'] ?? null,
                'access_token' => $socialUser['access_token'] ?? null,
                'refresh_token' => $socialUser['refresh_token'] ?? null,
                'expires_in' => $socialUser['expires_in'] ?? null,
                'token_expires_at' => $this->calculateExpiresAt($socialUser['expires_in'] ?? null),
                'raw_data' => $socialUser['raw'] ?? [],
            ]);
        }
        
        // 触发绑定事件
        Event::dispatch(new SocialAccountBound($user->toArray(), $provider));
        
        return $socialAccount;
    }

    /**
     * 解绑社交账号
     */
    public function unbindAccount(User $user, string $provider): bool
    {
        $account = $this->repository->findByUserAndProvider($user->id, $provider);
        
        if (!$account) {
            throw new \Exception('未找到绑定的账号');
        }
        
        return $this->repository->delete($account['id']);
    }

    /**
     * 获取用户绑定的社交账号列表
     */
    public function getUserAccounts(int $userId): array
    {
        return $this->repository->getUserAccounts($userId);
    }

    /**
     * 从社交平台信息创建用户
     */
    private function createUserFromSocial(string $provider, array $socialUser): User
    {
        // 生成唯一的用户名
        $username = $this->generateUniqueUsername($provider, $socialUser);
        
        // 生成随机密码（用户可以后续修改）
        $password = Hash::make(Str::random(32));
        
        $userData = [
            'username' => $username,
            'email' => $socialUser['email'] ?? null,
            'password' => $password,
            'avatar' => $socialUser['avatar'] ?? null,
            'role' => 'user',
            'status' => 1,
        ];
        
        $user = User::create($userData);
        
        // 创建用户档案
        $user->profile()->create([
            'nickname' => $socialUser['nickname'] ?? $username,
        ]);
        
        return $user;
    }

    /**
     * 生成唯一的用户名
     */
    private function generateUniqueUsername(string $provider, array $socialUser): string
    {
        $baseUsername = $provider . '_' . ($socialUser['nickname'] ?? $socialUser['id']);
        $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $baseUsername);
        
        $username = $baseUsername;
        $counter = 1;
        
        // ✅ 修复: username -> name
        while (User::where('name', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }
        
        return $username;
    }

    /**
     * 计算token过期时间
     */
    private function calculateExpiresAt(?int $expiresIn): ?\DateTime
    {
        if (!$expiresIn) {
            return null;
        }
        
        return now()->addSeconds($expiresIn);
    }
}

