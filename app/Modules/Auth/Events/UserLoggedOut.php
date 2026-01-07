<?php

namespace App\Modules\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * User Logged Out Event
 * 
 * 用户登出事件
 */
class UserLoggedOut
{
    use Dispatchable, SerializesModels;

    public array $user;
    public string $loggedOutAt;

    public function __construct(array $user)
    {
        $this->user = $user;
        $this->loggedOutAt = now()->toISOString();
    }
}

