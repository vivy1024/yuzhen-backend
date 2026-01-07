<?php

namespace App\Modules\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * User Logged In Event
 * 
 * 用户登录事件
 */
class UserLoggedIn
{
    use Dispatchable, SerializesModels;

    public array $user;
    public string $ip;
    public string $loggedInAt;

    public function __construct(array $user, string $ip)
    {
        $this->user = $user;
        $this->ip = $ip;
        $this->loggedInAt = now()->toISOString();
    }
}

