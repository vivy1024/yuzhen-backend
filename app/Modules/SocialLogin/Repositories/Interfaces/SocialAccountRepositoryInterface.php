<?php

namespace App\Modules\SocialLogin\Repositories\Interfaces;

/**
 * Social Account Repository Interface
 * 
 * 社交账号数据访问层接口
 */
interface SocialAccountRepositoryInterface
{
    /**
     * 根据平台和平台用户ID查找
     */
    public function findByProviderAndId(string $provider, string $providerUserId): ?array;

    /**
     * 根据用户ID和平台查找
     */
    public function findByUserAndProvider(int $userId, string $provider): ?array;

    /**
     * 获取用户的所有社交账号
     */
    public function getUserAccounts(int $userId): array;

    /**
     * 根据ID查找
     */
    public function findById(int $id): ?array;

    /**
     * 创建社交账号
     */
    public function create(array $data): array;

    /**
     * 更新社交账号
     */
    public function update(int $id, array $data): bool;

    /**
     * 删除社交账号
     */
    public function delete(int $id): bool;
}

