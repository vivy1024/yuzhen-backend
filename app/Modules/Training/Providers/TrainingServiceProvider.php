<?php

namespace App\Modules\Training\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Training\Repositories\Interfaces\TrainingPlanRepositoryInterface;
use App\Modules\Training\Repositories\TrainingPlanRepository;
use App\Modules\Training\Repositories\Interfaces\TrainingSessionRepositoryInterface;
use App\Modules\Training\Repositories\TrainingSessionRepository;

/**
 * Training Service Provider
 * 
 * 训练模块服务提供者
 */
class TrainingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 绑定Repository接口到实现
        $this->app->bind(
            TrainingPlanRepositoryInterface::class,
            TrainingPlanRepository::class
        );
        
        $this->app->bind(
            TrainingSessionRepositoryInterface::class,
            TrainingSessionRepository::class
        );
    }

    public function boot(): void
    {
        // 加载模块路由 - 使用统一的routes/modules目录
        $this->loadRoutesFrom(base_path('routes/modules/training.php'));
    }
}

