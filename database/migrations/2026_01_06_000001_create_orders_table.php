<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 订单表迁移
 * 
 * 支持收款码+截图上传的打赏支付方式
 * MVP阶段：用户扫码付款后上传截图，管理员后台审核开通
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            
            // 订单基本信息
            $table->string('order_no', 32)->unique()->comment('订单号');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('membership_id')->constrained()->onDelete('cascade');
            
            // 金额信息
            $table->decimal('amount', 10, 2)->comment('订单金额');
            $table->decimal('actual_amount', 10, 2)->nullable()->comment('实付金额');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('优惠金额');
            
            // 订单状态: pending(待支付), paid(已支付), cancelled(已取消), refunded(已退款), reviewing(审核中)
            $table->string('status', 20)->default('pending')->comment('订单状态');
            
            // 支付方式: wechat(微信), alipay(支付宝)
            $table->string('pay_method', 20)->nullable()->comment('支付方式');
            $table->string('pay_trade_no', 64)->nullable()->comment('支付交易号');
            $table->timestamp('paid_at')->nullable()->comment('支付时间');
            
            // 支付截图（收款码支付方式）
            $table->string('payment_proof_url')->nullable()->comment('支付截图URL');
            $table->timestamp('proof_uploaded_at')->nullable()->comment('截图上传时间');
            
            // 审核信息
            $table->foreignId('reviewer_id')->nullable()->comment('审核人ID');
            $table->timestamp('reviewed_at')->nullable()->comment('审核时间');
            $table->text('review_note')->nullable()->comment('审核备注');
            
            // 其他
            $table->text('remark')->nullable()->comment('订单备注');
            $table->timestamps();
            
            // 索引
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
