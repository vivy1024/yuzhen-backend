<?php

namespace App\Modules\Auth\Services;

use App\Modules\User\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JWT Service
 * 
 * JWT令牌管理服务
 * 
 * 注意：这是一个简化实现，生产环境建议使用 tymon/jwt-auth 包
 */
class JwtService
{
    protected string $secret;
    protected int $ttl;
    protected int $refreshTtl;
    
    public function __construct()
    {
        $this->secret = config('auth.jwt_secret', env('JWT_SECRET', 'your-secret-key'));
        $this->ttl = config('auth.jwt_ttl', 3600); // 1小时
        $this->refreshTtl = config('auth.jwt_refresh_ttl', 604800); // 7天
    }

    /**
     * 生成访问令牌
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

