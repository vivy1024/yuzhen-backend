<?php

namespace App\Modules\Auth\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Auth\Services\AliyunDypnsClient;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Auth\Services\SmsService;

/**
 * 认证模块服务提供者
 * 
 * 注册认证相关的服务到Laravel服务容器
 * 
 * @version 1.0.0
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 注册阿里云DYPNS短信客户端（单例）
        $this->app->singleton(AliyunDypnsClient::class, function ($app) {
            return new AliyunDypnsClient();
        });

        // 注册JWT服务（单例）
        $this->app->singleton(JwtService::class, function ($app) {
            return new JwtService();
        });

        // 注册短信服务（单例）
        $this->app->singleton(SmsService::class, function ($app) {
            return new SmsService(
                $app->make(AliyunDypnsClient::class),
                $app->make(JwtService::class)
            );
        });
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        //
    }
}
