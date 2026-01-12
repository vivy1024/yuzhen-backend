<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Membership\Controllers\MembershipController;
use App\Modules\Membership\Controllers\OrderController;

/**
 * Membership Module Routes
 * 
 * 会员模块路由
 * 
 * 前缀: /api/membership
 */

Route::prefix('membership')->group(function () {
    
    // ========== 公开路由 ==========
    
    // 获取会员系统配置（前端用于控制UI显示）
    Route::get('/config', [MembershipController::class, 'getConfig']);
    
    // 获取所有会员等级
    Route::get('/tiers', [MembershipController::class, 'index']);
    
    // 获取所有可购买的会员套餐
    Route::get('/plans', [MembershipController::class, 'plans']);
    
    // 获取支付截图（图片代理，绕过ORB限制，无需认证）
    Route::get('/orders/{orderNo}/proof-image', [OrderController::class, 'getProofImage']);
    
    
    // ========== 需要认证的路由 ==========
    Route::middleware(['jwt.auth'])->group(function () {
        
        // 获取当前用户会员信息
        Route::get('/current', [MembershipController::class, 'getCurrent']);
        
        // 检查权限
        Route::post('/check-permission', [MembershipController::class, 'checkPermission']);
        
        // 订单管理
        Route::prefix('orders')->group(function () {
            // 创建订单
            Route::post('/', [OrderController::class, 'create']);
            
            // 获取用户订单列表
            Route::get('/', [OrderController::class, 'index']);
            
            // 查询订单详情
            Route::get('/{orderNo}', [OrderController::class, 'show']);
            
            // 取消订单
            Route::post('/{orderId}/cancel', [OrderController::class, 'cancel']);
            
            // 删除订单（仅待支付订单）
            Route::delete('/{orderId}', [OrderController::class, 'delete']);
            
            // 上传支付截图（收款码支付方式）
            Route::post('/{orderNo}/upload-proof', [OrderController::class, 'uploadPaymentProof']);
        });
        
        // 获取收款码
        Route::get('/payment-qrcodes', [OrderController::class, 'getPaymentQRCodes']);
        
    });
    
    
    // ========== 管理员路由 ==========
    Route::middleware(['jwt.auth', 'admin'])->group(function () {
        
        // 会员统计
        Route::get('/stats', [MembershipController::class, 'getStats']);
        
    });
    
});

