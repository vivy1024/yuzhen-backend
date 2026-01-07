<?php

namespace App\Modules\SocialLogin\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Social Login Successful Event
 * 
 * 社交登录成功事件
 */
class SocialLoginSuccessful
{
    use Dispatchable, SerializesModels;

    public array $user;
    public string $provider;
    public string $loginAt;

    public function __construct(array $user, string $provider)
    {
        $this->user = $user;
        $this->provider = $provider;
        $this->loginAt = now()->toISOString();
    }
}

