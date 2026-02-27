<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Auth\Controllers\RegisterController;
use App\Modules\Auth\Controllers\SmsController;
use App\Modules\Auth\Controllers\EmailController;
use App\Modules\Auth\Controllers\PermissionController;

/**
 * Auth Module Routes
 * 
 * 认证模块路由
 * 
 * 前缀: /api/auth
 */

Route::prefix('auth')->group(function () {
    
    // 公开路由（无需认证）
    
    // 用户注册
    Route::post('/register', [RegisterController::class, 'register']);
    // 手机号注册
    Route::post('/register/phone', [RegisterController::class, 'registerByPhone']);
    
    // 用户登录
    Route::post('/login', [LoginController::class, 'login']);
    
    // 刷新Token
    Route::post('/refresh', [LoginController::class, 'refresh']);
    
    // 短信验证码相关路由（移除路由层限流，只使用业务层限流）
    Route::prefix('sms')->group(function () {
        // 发送验证码
        Route::post('/send', [SmsController::class, 'send']);
        // 验证验证码
        Route::post('/verify', [SmsController::class, 'verify']);
        // 手机号验证码登录
        Route::post('/login', [SmsController::class, 'login']);
        // 检查手机号是否已注册
        Route::get('/check-phone', [SmsController::class, 'checkPhone']);
    });
    
    // 邮箱验证码相关路由
    Route::prefix('email')->group(function () {
        // 发送验证码
        Route::post('/send', [EmailController::class, 'send']);
        // 验证验证码
        Route::post('/verify', [EmailController::class, 'verify']);
        // 邮箱验证码登录
        Route::post('/login', [EmailController::class, 'login']);
        // 重置密码
        Route::post('/reset-password', [EmailController::class, 'resetPassword']);
        // 检查邮箱是否已注册
        Route::get('/check', [EmailController::class, 'checkEmail']);
    });
    
    // 需要认证的路由
    Route::middleware(['jwt.auth'])->group(function () {

        // 用户登出
        Route::post('/logout', [LoginController::class, 'logout']);

        // 权限相关
        Route::prefix('permissions')->group(function () {
            Route::get('/', [PermissionController::class, 'index']);
            Route::get('/check', [PermissionController::class, 'check']);
            Route::post('/refresh-token', [PermissionController::class, 'refreshToken']);
        });

    });
    
});

