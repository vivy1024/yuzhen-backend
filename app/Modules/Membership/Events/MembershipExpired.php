<?php

namespace App\Modules\Membership\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Membership Expired Event
 * 
 * 会员过期事件
 */
class MembershipExpired
{
    use Dispatchable, SerializesModels;

    public int $userId;
    public string $expiredAt;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->expiredAt = now()->toISOString();
    }
}

