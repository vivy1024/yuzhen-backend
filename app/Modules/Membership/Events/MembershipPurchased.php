<?php

namespace App\Modules\Membership\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Membership Purchased Event
 * 
 * 会员购买成功事件
 */
class MembershipPurchased
{
    use Dispatchable, SerializesModels;

    public int $userId;
    public int $membershipId;
    public string $purchasedAt;

    public function __construct(int $userId, int $membershipId)
    {
        $this->userId = $userId;
        $this->membershipId = $membershipId;
        $this->purchasedAt = now()->toISOString();
    }
}

