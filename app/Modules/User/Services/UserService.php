<?php

namespace App\Modules\User\Services;

use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use App\Modules\User\Events\UserRegistered;
use App\Modules\User\Events\UserProfileUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/**
 * User Service
 * 
 * 用户服务层
 */
class UserService
{
    protected UserRepositoryInterface $repository;
    
    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * 获取用户列表
     */
    public function getList(array $filters, int $page = 1, int $perPage = 20): array
    {
        return $this->repository->paginate($filters, $page, $perPage);
    }

    /**
     * 获取用户详情（包含关联数据）
     */
    public function getDetail(int $id): ?array
    {
        return $this->repository->findByIdWithRelations($id, ['profile', 'membership']);
    }

    /**
     * 创建用户
     */
    public function create(array $data): array
    {
        // 密码加密
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        
        // 创建用户
        $user = $this->repository->create($data);
        
        // 触发注册事件
        Event::dispatch(new UserRegistered($user));
        
        return $user;
    }

    /**
     * 更新用户信息
     */
    public function update(int $id, array $data): bool
    {
        // 如果更新密码，需要加密
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        
        $result = $this->repository->update($id, $data);
        
        if ($result) {
            $user = $this->repository->findById($id);
            Event::dispatch(new UserProfileUpdated($user));
        }
        
        return $result;
    }

    /**
     * 删除用户（软删除）
     */
    public function delete(int $id): bool
    {
        return $this->repository->softDelete($id);
    }

    /**
     * 根据邮箱查找用户
     */
    public function findByEmail(string $email): ?array
    {
        return $this->repository->findByEmail($email);
    }

    /**
     * 根据用户名查找用户
     */
    public function findByUsername(string $username): ?array
    {
        return $this->repository->findByUsername($username);
    }

    /**
     * 更新用户档案
     */
    public function updateProfile(int $userId, array $profileData): bool
    {
        return $this->repository->updateProfile($userId, $profileData);
    }

    /**
     * 获取用户统计信息
     */
    public function getStatistics(int $userId): array
    {
        // TODO: 实现统计逻辑
        return [
            'total_workouts' => 0,
            'total_duration' => 0,
            'total_calories' => 0,
            'streak_days' => 0,
        ];
    }
}

