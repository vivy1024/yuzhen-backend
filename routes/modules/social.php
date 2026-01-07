<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SocialLogin\Controllers\WechatLoginController;
use App\Modules\SocialLogin\Controllers\SocialAccountController;

/**
 * Social Login Module Routes
 * 
 * 社交登录模块路由
 * 
 * 前缀: /api/social
 */

Route::prefix('social')->group(function () {
    
    // ========== 微信登录 ==========
    
    // 发起微信登录
    Route::get('/login/wechat', [WechatLoginController::class, 'redirect']);
    
    // 微信登录回调
    Route::get('/callback/wechat', [WechatLoginController::class, 'callback']);
    
    
    // ========== 微博登录 ==========
    // TODO: 实现WeiboLoginController
    // Route::get('/login/weibo', [WeiboLoginController::class, 'redirect']);
    // Route::get('/callback/weibo', [WeiboLoginController::class, 'callback']);
    
    
    // ========== QQ登录 ==========
    // TODO: 实现QQLoginController
    // Route::get('/login/qq', [QQLoginController::class, 'redirect']);
    // Route::get('/callback/qq', [QQLoginController::class, 'callback']);
    
    
    // ========== Github登录 ==========
    // TODO: 实现GithubLoginController
    // Route::get('/login/github', [GithubLoginController::class, 'redirect']);
    // Route::get('/callback/github', [GithubLoginController::class, 'callback']);
    
    
    // ========== 需要认证的路由 ==========
    Route::middleware(['jwt.auth'])->group(function () {
        
        // 查看已绑定的社交账号
        Route::get('/accounts', [SocialAccountController::class, 'index']);
        
        // 绑定微信账号
        Route::post('/bind/wechat', [WechatLoginController::class, 'bind']);
        
        // 解绑微信账号
        Route::delete('/unbind/wechat', [WechatLoginController::class, 'unbind']);
        
        // TODO: 其他平台的绑定/解绑
        
    });
    
});

