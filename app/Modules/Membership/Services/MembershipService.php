<?php

namespace App\Modules\Membership\Services;

use App\Modules\Membership\Repositories\Interfaces\MembershipRepositoryInterface;
use App\Modules\Membership\Repositories\Interfaces\UserMembershipRepositoryInterface;
use App\Modules\Membership\Repositories\Interfaces\OrderRepositoryInterface;
use App\Modules\Membership\Models\Order;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\Membership\Events\MembershipPurchased;
use App\Modules\Membership\Events\MembershipExpired;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;

/**
 * Membership Service
 * 
 * 会员服务
 */
class MembershipService
{
    protected MembershipRepositoryInterface $membershipRepo;
    protected UserMembershipRepositoryInterface $userMembershipRepo;
    protected OrderRepositoryInterface $orderRepo;

    public function __construct(
        MembershipRepositoryInterface $membershipRepo,
        UserMembershipRepositoryInterface $userMembershipRepo,
        OrderRepositoryInterface $orderRepo
    ) {
        $this->membershipRepo = $membershipRepo;
        $this->userMembershipRepo = $userMembershipRepo;
        $this->orderRepo = $orderRepo;
    }

    /**
     * 获取所有会员等级
     */
    public function getAllMemberships(): array
    {
        return $this->membershipRepo->getActiveMemberships();
    }

    /**
     * 获取用户当前会员
     */
    public function getUserMembership(int $userId): ?array
    {
        return $this->userMembershipRepo->getActiveMembership($userId);
    }

    /**
     * 检查用户是否有权限
     */
    public function checkPermission(int $userId, string $feature): bool
    {
        $userMembership = $this->getUserMembership($userId);
        
        if (!$userMembership) {
            // 没有会员，只有免费权限
            return $this->checkFreePermission($feature);
        }

        $membership = $this->membershipRepo->findById($userMembership['membership_id']);
        
        return match($feature) {
            'unlock_all_exercises' => $membership['unlock_all_exercises'] ?? false,
            'ai_recommendation' => $membership['ai_recommendation'] ?? false,
            'data_analysis' => $membership['data_analysis'] ?? false,
            'coach_service' => $membership['coach_service'] ?? false,
            default => false,
        };
    }

    /**
     * 检查免费版权限
     */
    private function checkFreePermission(string $feature): bool
    {
        // 免费版只有基础功能
        return false;
    }

    /**
     * 创建订单
     */
    public function createOrder(int $userId, int $membershipId): array
    {
        $membership = $this->membershipRepo->findById($membershipId);
        
        if (!$membership) {
            throw new \Exception('会员等级不存在');
        }

        if (!$membership['is_active']) {
            throw new \Exception('会员等级已停用');
        }

        $orderData = [
            'order_no' => Order::generateOrderNo(),
            'user_id' => $userId,
            'membership_id' => $membershipId,
            'amount' => $membership['price'],
            'actual_amount' => $membership['price'], // 暂无优惠
            'discount_amount' => 0,
            'status' => 'pending',
        ];

        return $this->orderRepo->create($orderData);
    }

    /**
     * 处理支付成功
     */
    public function handlePaymentSuccess(int $orderId, string $tradeNo, string $payMethod): bool
    {
        return DB::transaction(function () use ($orderId, $tradeNo, $payMethod) {
            // 1. 更新订单状态
            $order = $this->orderRepo->findById($orderId);
            if (!$order || $order['status'] !== 'pending') {
                throw new \Exception('订单状态异常');
            }

            $this->orderRepo->markAsPaid($orderId, $tradeNo, $payMethod);

            // 2. 创建或更新用户会员
            $membership = $this->membershipRepo->findById($order['membership_id']);
            $this->activateUserMembership($order['user_id'], $order['membership_id'], $membership['duration_days'], $orderId);

            // 3. 触发事件
            Event::dispatch(new MembershipPurchased($order['user_id'], $order['membership_id']));

            return true;
        });
    }

    /**
     * 激活用户会员
     */
    private function activateUserMembership(int $userId, int $membershipId, int $durationDays, int $orderId): void
    {
        $existingMembership = $this->userMembershipRepo->getActiveMembership($userId);

        if ($existingMembership) {
            // 续费：在当前会员基础上延长
            $this->userMembershipRepo->extend($existingMembership['id'], $durationDays);
        } else {
            // 新购买
            $data = [
                'user_id' => $userId,
                'membership_id' => $membershipId,
                'started_at' => now(),
                'expires_at' => now()->addDays($durationDays),
                'is_active' => 1,
                'order_id' => $orderId,
            ];
            $this->userMembershipRepo->create($data);
        }
        
        // ✅ 清除缓存
        cache()->forget("user_membership:{$userId}");
    }

    /**
     * 根据订单激活会员（管理员审核通过后调用）
     */
    public function activateMembershipByOrder(int $orderId): bool
    {
        $order = Order::with('membership')->findOrFail($orderId);
        
        if ($order->status !== Order::STATUS_PAID) {
            throw new \Exception('订单未支付');
        }
        
        $membership = $order->membership;
        if (!$membership) {
            throw new \Exception('会员等级不存在');
        }
        
        $this->activateUserMembership(
            $order->user_id,
            $order->membership_id,
            $membership->duration_days,
            $orderId
        );
        
        return true;
    }

    /**
     * 取消订单
     */
    public function cancelOrder(int $orderId, int $userId): bool
    {
        $order = $this->orderRepo->findById($orderId);
        
        if (!$order) {
            throw new \Exception('订单不存在');
        }

        if ($order['user_id'] !== $userId) {
            throw new \Exception('无权操作此订单');
        }

        if ($order['status'] !== 'pending') {
            throw new \Exception('订单状态不允许取消');
        }

        return $this->orderRepo->cancel($orderId);
    }

    /**
     * 检查过期会员
     */
    public function checkExpiredMemberships(): int
    {
        $expiredCount = 0;
        $expiredMemberships = $this->userMembershipRepo->getExpiredMemberships();

        foreach ($expiredMemberships as $userMembership) {
            $this->userMembershipRepo->markAsExpired($userMembership['id']);
            Event::dispatch(new MembershipExpired($userMembership['user_id']));
            $expiredCount++;
        }

        return $expiredCount;
    }

    /**
     * 根据ID获取会员信息
     */
    public function getMembershipById(int $membershipId): ?array
    {
        return $this->membershipRepo->findById($membershipId);
    }

    /**
     * 获取会员统计
     */
    public function getMembershipStats(): array
    {
        return [
            'total_members' => $this->userMembershipRepo->getTotalActiveMembers(),
            'members_by_level' => $this->userMembershipRepo->getMembersByLevel(),
            'expiring_soon' => $this->userMembershipRepo->getExpiringSoonCount(),
            'revenue_today' => $this->orderRepo->getRevenueByDate(today()),
            'revenue_month' => $this->orderRepo->getRevenueByMonth(now()->format('Y-m')),
        ];
    }
}

