<?php

namespace App\Modules\User\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use App\Modules\User\Repositories\UserRepository;

/**
 * User Service Provider
 * 
 * 用户模块服务提供者
 */
class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 绑定Repository接口到实现
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );
    }

    public function boot(): void
    {
        // 路由已在 routes/api.php 中通过 require 引入

        // 加载模块迁移
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }
}

