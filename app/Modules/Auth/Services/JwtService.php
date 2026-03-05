<?php

namespace App\Modules\Auth\Services;

use App\Modules\User\Models\User;
use App\Services\PermissionService;
use App\Services\MembershipService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;

/**
 * JWT Service
 * 
 * JWT令牌管理服务
 * External JWT中包含用户权限Claims，供前端解析权限状态
 * 
 * @requirements 6.1 - Token刷新时包含新的权限信息
 */
class JwtService
{
    protected string $secret;
    protected int $ttl;
    protected int $refreshTtl;
    
    public function __construct()
    {
        $this->secret = config('auth.jwt_secret', '');
        if (empty($this->secret)) {
            throw new \RuntimeException('JWT_SECRET未配置，拒绝启动。请在.env中设置JWT_SECRET');
        }
        $this->ttl = config('auth.jwt_ttl', 3600); // 1小时
        $this->refreshTtl = config('auth.jwt_refresh_ttl', 604800); // 7天
    }

    /**
     * 生成访问令牌
     * 包含用户权限Claims（tier、permissions），供前端解析
     * 
     * @requirements 6.1
     */
    public function generateToken(User $user): string
    {
        $payload = [
            'iss' => config('app.url'), // 签发者
            'iat' => time(), // 签发时间
            'exp' => time() + $this->ttl, // 过期时间
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
        ];
        
        // 附加权限Claims到External JWT
        try {
            $permissionService = app(PermissionService::class);
            $membershipService = app(MembershipService::class);
            
            $tier = $membershipService->getUserTier($user->id);
            $permissions = $permissionService->getUserPermissions($user->id);
            
            $payload['tier'] = $tier;
            $payload['permissions'] = $permissions;
        } catch (\Throwable $e) {
            // 权限查询失败不影响JWT签发，降级为不包含权限Claims
            Log::warning('JwtService: 附加权限Claims失败，降级签发', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
        
        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /**
     * 生成刷新令牌
     */
    public function generateRefreshToken(User $user): string
    {
        $payload = [
            'iss' => config('app.url'),
            'iat' => time(),
            'exp' => time() + $this->refreshTtl,
            'user_id' => $user->id,
            'type' => 'refresh',
        ];
        
        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /**
     * 验证访问令牌
     */
    public function verifyToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 验证刷新令牌
     */
    public function verifyRefreshToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            $payload = (array) $decoded;
            
            // 检查是否是刷新令牌
            if (!isset($payload['type']) || $payload['type'] !== 'refresh') {
                return null;
            }
            
            return $payload;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 从令牌中提取用户ID
     */
    public function getUserIdFromToken(string $token): ?int
    {
        $payload = $this->verifyToken($token);
        return $payload['user_id'] ?? null;
    }
}

