<?php

namespace App\Modules\User\Repositories;

use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserProfile;
use App\Infrastructure\Database\Repositories\BaseRepository;

/**
 * User Repository
 * 
 * 用户数据访问层实现
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected $model = User::class;

    /**
     * 根据ID查找用户
     */
    public function findById(int $id): ?array
    {
        $user = User::find($id);
        return $user ? $user->toArray() : null;
    }

    /**
     * 根据ID查找用户（包含关联数据）
     */
    public function findByIdWithRelations(int $id, array $relations = []): ?array
    {
        $user = User::with($relations)->find($id);
        return $user ? $user->toArray() : null;
    }

    /**
     * 根据邮箱查找用户
     */
    public function findByEmail(string $email): ?array
    {
        $user = User::where('email', $email)->first();
        return $user ? $user->toArray() : null;
    }

    /**
     * 根据用户名查找用户
     */
    public function findByUsername(string $username): ?array
    {
        // ✅ 修复: username -> name
        $user = User::where('name', $username)->first();
        return $user ? $user->toArray() : null;
    }

    /**
     * 分页获取用户列表
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $query = User::query();
        
        // 应用筛选条件
        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                // ✅ 修复: username -> name
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('email', 'like', "%{$filters['search']}%");
                  // ->orWhere('phone', 'like', "%{$filters['search']}%");  // phone字段存在问题，暂时注释
            });
        }
        
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        
        return [
            'rows' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    /**
     * 创建用户
     */
    public function create(array $data): array
    {
        $user = User::create($data);
        
        // 自动创建用户档案
        if ($user) {
            UserProfile::create([
                'user_id' => $user->id,
            ]);
        }
        
        return $user->toArray();
    }

    /**
     * 更新用户
     */
    public function update(int $id, array $data): bool
    {
        return User::where('id', $id)->update($data) > 0;
    }

    /**
     * 软删除用户
     */
    public function softDelete(int $id): bool
    {
        $user = User::find($id);
        return $user ? $user->delete() : false;
    }

    /**
     * 更新用户档案
     */
    public function updateProfile(int $userId, array $profileData): bool
    {
        $profile = UserProfile::where('user_id', $userId)->first();
        
        // 过滤掉空的strength_data，避免覆盖已有数据
        if (isset($profileData['strength_data'])) {
            $strengthData = $profileData['strength_data'];
            // 如果是空数组或者所有值都是空的，则不更新
            if (empty($strengthData) || $this->isEmptyStrengthData($strengthData)) {
                unset($profileData['strength_data']);
            }
        }
        
        if ($profile) {
            return $profile->update($profileData);
        }
        
        // 如果档案不存在，创建新档案
        $profileData['user_id'] = $userId;
        UserProfile::create($profileData);
        return true;
    }
    
    /**
     * 检查力量数据是否为空
     * 
     * @param array $strengthData
     * @return bool
     */
    private function isEmptyStrengthData(array $strengthData): bool
    {
        foreach ($strengthData as $exercise => $data) {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (!is_null($value) && $value !== '' && $value !== 0) {
                        return false;
                    }
                }
            } elseif (!is_null($data) && $data !== '' && $data !== 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * 统计用户数量
     */
    public function count(array $filters = []): int
    {
        $query = User::query();
        
        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        return $query->count();
    }
}

