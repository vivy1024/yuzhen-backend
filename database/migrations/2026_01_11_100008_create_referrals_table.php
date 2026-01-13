<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 推荐关系表 - 会员自动化控制系统
 * 
 * 记录用户推荐关系和奖励发放
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 15.4
 */
return new class extends Migration
{
    public function up(): void
    {
        // 如果表已存在，跳过创建
        if (Schema::hasTable('referrals')) {
            return;
        }
        
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id')->comment('推荐人ID');
            $table->unsignedBigInteger('referee_id')->comment('被推荐人ID');
            $table->enum('status', ['registered', 'paid'])->default('registered')
                ->comment('状态：registered(已注册)/paid(已付费)');
            $table->boolean('reward_granted')->default(false)->comment('奖励是否已发放');
            $table->decimal('cashback_amount', 10, 2)->default(0)->comment('返现金额');
            $table->timestamp('created_at')->useCurrent()->comment('创建时间');
            
            // 外键约束
            $table->foreign('referrer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('referee_id')->references('id')->on('users')->onDelete('cascade');
            
            // 索引
            $table->index('referrer_id', 'idx_referrer_id');
            $table->index('referee_id', 'idx_referee_id');
            
            // 唯一约束：每个被推荐人只能有一个推荐人
            $table->unique('referee_id', 'unique_referee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
