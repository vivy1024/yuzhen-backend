<?php

use Illuminate\Support\Facades\Route;
use App\Modules\User\Controllers\UserController;

/**
 * User Module Routes
 * 
 * 用户模块路由
 * 
 * 前缀: /api/users
 */

Route::prefix('users')->group(function () {
    
    // 需要认证的路由（使用JWT认证）
    Route::middleware(['jwt.auth'])->group(function () {
        
        // 获取当前用户信息
        Route::get('/me', [UserController::class, 'me']);
        
        // 获取用户档案
        Route::get('/profile', [UserController::class, 'getProfile']);
        
        // 更新用户档案（支持PUT和POST）
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::post('/profile', [UserController::class, 'updateProfile']); // 兼容前端POST
        
        // 获取用户统计信息
        Route::get('/statistics', [UserController::class, 'statistics']);

        // FFMI历史记录
        Route::get('/profile/ffmi-history', [UserController::class, 'getFFMIHistory']);
        Route::post('/profile/ffmi-history', [UserController::class, 'saveFFMIHistory']);

        // 获取用户详情
        Route::get('/{id}', [UserController::class, 'show'])
            ->where('id', '[0-9]+');
        
        // 以下需要管理员权限
        Route::middleware(['role:admin'])->group(function () {
            
            // 获取用户列表
            Route::get('/', [UserController::class, 'index']);
            
            // 创建用户
            Route::post('/', [UserController::class, 'store']);
            
            // 更新用户
            Route::put('/{id}', [UserController::class, 'update']);
            
            // 删除用户
            Route::delete('/{id}', [UserController::class, 'destroy']);
            
        });
        
    });
    
});

