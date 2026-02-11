<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 重构user_credits表 - 积分体系改造
 * 
 * 将旧的"会员次数限制体系"改造为新的"积分制体系"
 * 新体系以Token消耗为基础计算积分，提供更灵活、透明的计费模式
 * 
 * 字段说明：
 * - daily_quota: 每日积分配额（免费用户10，暖心会员50，能量会员200）
 * - daily_consumed: 今日已消耗积分
 * - total_consumed: 历史总消耗积分
 * - last_reset_date: 上次重置日期（用于每日配额重置）
 * 
 * @version v2.0.0
 * @date 2026-02-05
 * @author 薛小川
 * @requirements 9.1, 9.4, 9.5
 */
return new class extends Migration
{
    public function up(): void
    {
        // 先删除旧表（如果存在）
        Schema::dropIfExists('user_credits');
        
        // 创建新的积分体系表
        Schema::create('user_credits', function (Blueprint $table) {
            $table->id();
            
            // 用户关联（唯一约束确保每个用户只有一条记录）
            $table->unsignedBigInteger('user_id')->unique()->comment('用户ID');
            
            // 积分配额字段
            $table->unsignedInteger('daily_quota')->default(10)->comment('每日积分配额（免费10/暖心50/能量200）');
            $table->unsignedInteger('daily_consumed')->default(0)->comment('今日已消耗积分');
            $table->unsignedBigInteger('total_consumed')->default(0)->comment('历史总消耗积分');
            
            // 配额重置日期
            $table->date('last_reset_date')->comment('上次配额重置日期');
            
            // 时间戳
            $table->timestamps();
            
            // 外键约束
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            // 索引优化
            $table->index('user_id', 'idx_user_credits_user_id');
            $table->index('last_reset_date', 'idx_user_credits_last_reset');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_credits');
        
        // 回滚时恢复旧表结构
        Schema::create('user_credits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->comment('用户ID');
            $table->unsignedInteger('dag_credits')->default(0)->comment('DAG额外次数');
            $table->unsignedInteger('agent_credits')->default(0)->comment('Agent额外次数');
            $table->timestamp('updated_at')->nullable()->comment('最后更新时间');
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
