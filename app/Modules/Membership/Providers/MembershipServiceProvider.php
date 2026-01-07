<?php

namespace App\Modules\Membership\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Membership\Repositories\Interfaces\MembershipRepositoryInterface;
use App\Modules\Membership\Repositories\MembershipRepository;
use App\Modules\Membership\Repositories\Interfaces\UserMembershipRepositoryInterface;
use App\Modules\Membership\Repositories\UserMembershipRepository;
use App\Modules\Membership\Repositories\Interfaces\OrderRepositoryInterface;
use App\Modules\Membership\Repositories\OrderRepository;

/**
 * Membership Service Provider
 * 
 * 会员模块服务提供者
 */
class MembershipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 绑定Repository接口到实现
        $this->app->bind(
            MembershipRepositoryInterface::class,
            MembershipRepository::class
        );

        $this->app->bind(
            UserMembershipRepositoryInterface::class,
            UserMembershipRepository::class
        );

        $this->app->bind(
            OrderRepositoryInterface::class,
            OrderRepository::class
        );
    }

    public function boot(): void
    {
        // 路由已在 routes/api.php 中通过 require 引入
    }
}

