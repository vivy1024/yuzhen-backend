<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 会员订单表 - 会员自动化控制系统
 * 
 * 记录用户购买会员套餐的订单
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 10.1
 */
return new class extends Migration
{
    public function up(): void
    {
        // 如果表已存在，跳过创建
        if (Schema::hasTable('membership_orders')) {
            return;
        }
        
        Schema::create('membership_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 32)->unique()->comment('订单号');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('membership_id')->comment('会员套餐ID');
            $table->decimal('amount', 10, 2)->comment('实付金额');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('优惠金额');
            $table->string('pay_method', 20)->nullable()->comment('支付方式：wechat/alipay/manual');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'cancelled'])
                ->default('pending')
                ->comment('订单状态');
            $table->timestamp('paid_at')->nullable()->comment('支付时间');
            $table->timestamp('created_at')->useCurrent()->comment('创建时间');
            
            // 外键约束
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('membership_id')->references('id')->on('memberships');
            
            // 索引
            $table->index('user_id', 'idx_user_id');
            $table->index('status', 'idx_status');
            $table->index('created_at', 'idx_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_orders');
    }
};
