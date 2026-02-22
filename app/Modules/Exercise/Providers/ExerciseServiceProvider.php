<?php

namespace App\Modules\Exercise\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Exercise\Repositories\Interfaces\ExerciseRepositoryInterface;
use App\Modules\Exercise\Repositories\ExerciseRepository;

/**
 * Exercise Service Provider
 * 
 * 动作库模块服务提供者
 */
class ExerciseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 绑定Repository接口到实现
        $this->app->bind(
            ExerciseRepositoryInterface::class,
            ExerciseRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 路由已在 routes/api.php 中通过 require 引入

        // 加载模块迁移
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }
}

