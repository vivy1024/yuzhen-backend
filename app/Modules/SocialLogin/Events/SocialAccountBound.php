<?php

namespace App\Modules\SocialLogin\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Social Account Bound Event
 * 
 * 社交账号绑定事件
 */
class SocialAccountBound
{
    use Dispatchable, SerializesModels;

    public array $user;
    public string $provider;
    public string $boundAt;

    public function __construct(array $user, string $provider)
    {
        $this->user = $user;
        $this->provider = $provider;
        $this->boundAt = now()->toISOString();
    }
}

