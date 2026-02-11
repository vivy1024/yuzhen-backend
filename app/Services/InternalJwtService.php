<?php

namespace App\Services;

use App\Modules\User\Models\User;
use App\Services\MembershipService;
use App\Services\PermissionService;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Log;

/**
 * InternalJwtService - 内部JWT签发服务
 * 
 * 为PHP后端（Auth Gateway）与DAML-RAG（Protected Service）之间的
 * 服务间通信签发短期Internal JWT，携带用户身份和权限声明。
 * 
 * JWT使用HS256签名，过期时间不超过60秒，密钥与External JWT独立。
 * 
 * @version v1.0.0
 * @date 2026-02-11
 * @requirements 2.1, 2.2, 2.3, 2.6
 */
class InternalJwtService
{
    private string $secret;
    private int $ttl;
    private string $issuer;
    private PermissionService $permissionService;
    private MembershipService $membershipService;

    public function __construct(
        PermissionService $permissionService,
        MembershipService $membershipService
    ) {
        $this->secret = config('auth.internal_jwt_secret', '');
        $this->ttl = (int) config('auth.internal_jwt_ttl', 60);
        $this->issuer = config('auth.internal_jwt_issuer', 'yuzhen-auth-gateway');
        $this->permissionService = $permissionService;
        $this->membershipService = $membershipService;

        // 安全检查：TTL不超过60秒
        if ($this->ttl > 60) {
            $this->ttl = 60;
        }
    }

    /**
     * 构建Permission Claims
     * 
     * 包含sub、tier、permissions、daily_dag_limit、daily_agent_limit、iat、exp、iss
     * 
     * @param int $userId 用户ID
     * @return array Permission Claims数组
     */
    public function buildClaims(int $userId): array
    {
        $user = User::findOrFail($userId);
        $tier = $this->membershipService->getUserTier($userId);
        $permissions = $this->permissionService->getUserPermissions($userId);
        $limits = $this->membershipService->getEffectiveLimits($userId);

        $now = time();

        return [
            'sub' => $userId,
            'tier' => $tier,
            'permissions' => $permissions,
            'daily_dag_limit' => $limits['daily_dag_limit'] ?? 5,
            'daily_agent_limit' => $limits['daily_agent_limit'] ?? 0,
            'iat' => $now,
            'exp' => $now + $this->ttl,
            'iss' => $this->issuer,
        ];
    }

    /**
     * 为服务间通信签发Internal JWT
     * 
     * 使用HS256签名，密钥与External JWT独立
     * 
     * @param int $userId 用户ID
     * @return string 签名的JWT字符串
     * @throws \InvalidArgumentException 密钥未配置时
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 用户不存在时
     */
    public function issueToken(int $userId): string
    {
        $this->validateSecret();

        $claims = $this->buildClaims($userId);
        $token = JWT::encode($claims, $this->secret, 'HS256');

        Log::debug('InternalJwtService: JWT已签发', [
            'user_id' => $userId,
            'tier' => $claims['tier'],
            'exp' => $claims['exp'],
        ]);

        return $token;
    }

    /**
     * 验证密钥配置
     * 
     * 确保Internal JWT密钥已配置且与External JWT密钥不同
     * 
     * @throws \InvalidArgumentException
     */
    private function validateSecret(): void
    {
        if (empty($this->secret)) {
            throw new \InvalidArgumentException(
                'INTERNAL_JWT_SECRET未配置，请在.env中设置'
            );
        }

        if (strlen($this->secret) < 32) {
            throw new \InvalidArgumentException(
                'INTERNAL_JWT_SECRET长度不足，至少需要32字符'
            );
        }

        $externalSecret = config('auth.jwt_secret', '');
        if (!empty($externalSecret) && $this->secret === $externalSecret) {
            throw new \InvalidArgumentException(
                'INTERNAL_JWT_SECRET不能与JWT_SECRET（External JWT密钥）相同'
            );
        }
    }

    /**
     * 获取当前TTL配置（秒）
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * 获取签发者标识
     */
    public function getIssuer(): string
    {
        return $this->issuer;
    }
}
