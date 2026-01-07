<?php

namespace App\Modules\Membership\Repositories;

use App\Modules\Membership\Repositories\Interfaces\UserMembershipRepositoryInterface;
use App\Modules\Membership\Models\UserMembership;
use App\Infrastructure\Database\Repositories\BaseRepository;

class UserMembershipRepository extends BaseRepository implements UserMembershipRepositoryInterface
{
    protected $model = UserMembership::class;

    public function create(array $data): array
    {
        $userMembership = UserMembership::create($data);
        return $userMembership->toArray();
    }

    public function getActiveMembership(int $userId): ?array
    {
        // ✅ 性能优化：使用缓存（10分钟TTL）
        $cacheKey = "user_membership:{$userId}";
        
        return cache()->remember($cacheKey, 600, function () use ($userId) {
            $membership = UserMembership::active()
                ->with('membership') // ✅ 预加载关联数据，避免N+1查询
                ->where('user_id', $userId)
                ->first();
            
            if (!$membership) {
                return null;
            }
            
            // ✅ 将数据转换为数组并添加完整的会员信息
            $data = $membership->toArray();
            $membershipInfo = $membership->membership;
            
            if ($membershipInfo) {
                $data['tier'] = $membershipInfo->tier ?? 'free';
                $data['tier_name'] = $membershipInfo->name_zh ?? $membershipInfo->name ?? '免费用户';
                $data['features'] = $membershipInfo->features ?? [];
                $data['limits'] = $membershipInfo->limits ?? [];
            } else {
                $data['tier'] = 'free';
                $data['tier_name'] = '免费用户';
                $data['features'] = [];
                $data['limits'] = [];
            }
            
            return $data;
        });
    }

    public function extend(int $id, int $days): bool
    {
        $userMembership = UserMembership::find($id);
        
        if (!$userMembership) {
            return false;
        }

        $userMembership->renew($days);
        
        // ✅ 清除缓存
        cache()->forget("user_membership:{$userMembership->user_id}");
        
        return true;
    }

    public function markAsExpired(int $id): bool
    {
        $userMembership = UserMembership::find($id);
        if ($userMembership) {
            // ✅ 清除缓存
            cache()->forget("user_membership:{$userMembership->user_id}");
        }
        
        return UserMembership::where('id', $id)
            ->update(['is_active' => 0]) > 0;
    }

    public function getExpiredMemberships(): array
    {
        return UserMembership::where('is_active', 1)
            ->where('expires_at', '<', now())
            ->get()
            ->toArray();
    }

    public function getTotalActiveMembers(): int
    {
        return UserMembership::active()->count();
    }

    public function getMembersByLevel(): array
    {
        return UserMembership::active()
            ->selectRaw('membership_id, COUNT(*) as count')
            ->groupBy('membership_id')
            ->get()
            ->toArray();
    }

    public function getExpiringSoonCount(): int
    {
        return UserMembership::expiringSoon()->count();
    }
}

