<?php

namespace App\Modules\Food\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Food\Repositories\Interfaces\FoodRepositoryInterface;
use App\Modules\Food\Repositories\FoodRepository;

/**
 * Food Service Provider
 * 
 * 食物库模块服务提供者
 * 
 * @version 1.0.0
 * @date 2026-01-04
 */
class FoodServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 绑定Repository接口到实现
        $this->app->bind(
            FoodRepositoryInterface::class,
            FoodRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 路由已在 routes/api.php 中通过 require 引入
    }
}
