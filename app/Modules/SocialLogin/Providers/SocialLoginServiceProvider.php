<?php

namespace App\Modules\SocialLogin\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\SocialLogin\Repositories\Interfaces\SocialAccountRepositoryInterface;
use App\Modules\SocialLogin\Repositories\SocialAccountRepository;
use Overtrue\Socialite\SocialiteManager;

/**
 * Social Login Service Provider
 * 
 * 社交登录模块服务提供者
 */
class SocialLoginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 绑定Repository接口到实现
        $this->app->bind(
            SocialAccountRepositoryInterface::class,
            SocialAccountRepository::class
        );
        
        // 注册Socialite Manager
        $this->app->singleton(SocialiteManager::class, function ($app) {
            return new SocialiteManager(config('socialite'));
        });
    }

    public function boot(): void
    {
        // 路由已在 routes/api.php 中通过 require 引入
        // 不需要在这里重复加载
    }
}

