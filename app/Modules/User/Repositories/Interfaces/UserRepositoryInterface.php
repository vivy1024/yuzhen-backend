<?php

namespace App\Modules\User\Repositories\Interfaces;

/**
 * User Repository Interface
 * 
 * 用户数据访问层接口
 */
interface UserRepositoryInterface
{
    /**
     * 根据ID查找用户
     */
    public function findById(int $id): ?array;

    /**
     * 根据ID查找用户（包含关联数据）
     */
    public function findByIdWithRelations(int $id, array $relations = []): ?array;

    /**
     * 根据邮箱查找用户
     */
    public function findByEmail(string $email): ?array;

    /**
     * 根据用户名查找用户
     */
    public function findByUsername(string $username): ?array;

    /**
     * 分页获取用户列表
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /**
     * 创建用户
     */
    public function create(array $data): array;

    /**
     * 更新用户
     */
    public function update(int $id, array $data): bool;

    /**
     * 软删除用户
     */
    public function softDelete(int $id): bool;

    /**
     * 更新用户档案
     */
    public function updateProfile(int $userId, array $profileData): bool;

    /**
     * 统计用户数量
     */
    public function count(array $filters = []): int;
}

