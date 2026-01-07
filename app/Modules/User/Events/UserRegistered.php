<?php

namespace App\Modules\User\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * User Registered Event
 * 
 * 用户注册事件
 */
class UserRegistered
{
    use Dispatchable, SerializesModels;

    public array $user;
    public string $registeredAt;

    public function __construct(array $user)
    {
        $this->user = $user;
        $this->registeredAt = now()->toISOString();
    }
}

