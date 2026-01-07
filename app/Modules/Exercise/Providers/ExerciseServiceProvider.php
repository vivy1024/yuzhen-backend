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
        // 加载模块路由 - 使用统一的routes/modules目录
        $this->loadRoutesFrom(base_path('routes/modules/exercise.php'));
        
        // 加载模块迁移
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        
        // 注册事件监听器
        // Event::listen(ExerciseCreated::class, UpdateExerciseCache::class);
    }
}

