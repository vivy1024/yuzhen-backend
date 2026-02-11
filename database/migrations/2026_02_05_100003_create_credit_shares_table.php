<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建credit_shares表 - 积分分享记录（阶段2预留）
 * 
 * 记录能量会员分享积分给好友的记录，包括：
 * - 发送者和接收者
 * - 分享的积分数量
 * - 分享留言
 * 
 * 分享规则：
 * - 只有能量会员可以分享积分
 * - 每日分享给同一用户上限50积分
 * - 分享会同时创建双方的流水记录
 * 
 * @version v1.0.0
 * @date 2026-02-05
 * @author 薛小川
 * @requirements 9.3, 9.4, 9.5
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_shares', function (Blueprint $table) {
            $table->id();
            
            // 发送者（分享积分的用户，必须是能量会员）
            $table->unsignedBigInteger('sender_id')->comment('发送者用户ID（能量会员）');
            
            // 接收者（获得积分的用户）
            $table->unsignedBigInteger('receiver_id')->comment('接收者用户ID');
            
            // 分享积分数量
            $table->integer('credits')->comment('分享的积分数量（正整数）');
            
            // 分享留言
            $table->string('message', 255)->nullable()->comment('分享留言');
            
            // 时间戳（只需要created_at，分享记录不可修改）
            $table->timestamp('created_at')->useCurrent()->comment('创建时间');
            
            // 外键约束
            $table->foreign('sender_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            $table->foreign('receiver_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            // 索引优化
            // 1. 发送者索引 - 查询用户分享出去的积分记录
            $table->index('sender_id', 'idx_credit_shares_sender');
            
            // 2. 接收者索引 - 查询用户收到的积分记录
            $table->index('receiver_id', 'idx_credit_shares_receiver');
            
            // 3. 创建时间索引 - 按时间范围查询
            $table->index('created_at', 'idx_credit_shares_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_shares');
    }
};
