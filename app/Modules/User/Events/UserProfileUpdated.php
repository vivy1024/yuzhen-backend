<?php

namespace App\Modules\User\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * User Profile Updated Event
 * 
 * 用户档案更新事件
 */
class UserProfileUpdated
{
    use Dispatchable, SerializesModels;

    public array $user;
    public string $updatedAt;

    public function __construct(array $user)
    {
        $this->user = $user;
        $this->updatedAt = now()->toISOString();
    }
}

