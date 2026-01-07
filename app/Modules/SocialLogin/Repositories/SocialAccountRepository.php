<?php

namespace App\Modules\SocialLogin\Repositories;

use App\Modules\SocialLogin\Repositories\Interfaces\SocialAccountRepositoryInterface;
use App\Modules\SocialLogin\Models\SocialAccount;
use App\Infrastructure\Database\Repositories\BaseRepository;

/**
 * Social Account Repository
 * 
 * 社交账号数据访问层实现
 */
class SocialAccountRepository extends BaseRepository implements SocialAccountRepositoryInterface
{
    protected $model = SocialAccount::class;

    /**
     * 根据平台和平台用户ID查找
     */
    public function findByProviderAndId(string $provider, string $providerUserId): ?array
    {
        $account = SocialAccount::where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();
        
        return $account ? $account->toArray() : null;
    }

    /**
     * 根据用户ID和平台查找
     */
    public function findByUserAndProvider(int $userId, string $provider): ?array
    {
        $account = SocialAccount::where('user_id', $userId)
            ->where('provider', $provider)
            ->first();
        
        return $account ? $account->toArray() : null;
    }

    /**
     * 获取用户的所有社交账号
     */
    public function getUserAccounts(int $userId): array
    {
        return SocialAccount::where('user_id', $userId)
            ->select([
                'id',
                'provider',
                'provider_nickname',
                'provider_avatar',
                'created_at',
                'updated_at'
            ])
            ->get()
            ->toArray();
    }

    /**
     * 根据ID查找
     */
    public function findById(int $id): ?array
    {
        $account = SocialAccount::find($id);
        return $account ? $account->toArray() : null;
    }

    /**
     * 创建社交账号
     */
    public function create(array $data): array
    {
        $account = SocialAccount::create($data);
        return $account->toArray();
    }

    /**
     * 更新社交账号
     */
    public function update(int $id, array $data): bool
    {
        return SocialAccount::where('id', $id)->update($data) > 0;
    }

    /**
     * 删除社交账号
     */
    public function delete(int $id): bool
    {
        return SocialAccount::destroy($id) > 0;
    }
}

