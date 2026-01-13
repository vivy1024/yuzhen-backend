<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 用量统计表 - 会员自动化控制系统
 * 
 * 记录用户每日AI查询次数（DAG模式和Agent模式分开统计）
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 8.1
 */
return new class extends Migration
{
    public function up(): void
    {
        // 如果旧表存在，先删除外键约束再删除表
        if (Schema::hasTable('user_bonus_credits')) {
            Schema::table('user_bonus_credits', function (Blueprint $table) {
                // 尝试删除外键约束（如果存在）
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Exception $e) {
                    // 忽略外键不存在的错误
                }
            });
            Schema::dropIfExists('user_bonus_credits');
        }
        
        if (Schema::hasTable('user_usage_stats')) {
            Schema::table('user_usage_stats', function (Blueprint $table) {
                // 尝试删除外键约束（如果存在）
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Exception $e) {
                    // 忽略外键不存在的错误
                }
            });
            Schema::dropIfExists('user_usage_stats');
        }
        
        // 如果 usage_stats 表已存在，跳过创建
        if (Schema::hasTable('usage_stats')) {
            return;
        }
        
        Schema::create('usage_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->date('date')->comment('统计日期');
            $table->unsignedInteger('dag_queries')->default(0)->comment('DAG模式查询次数');
            $table->unsignedInteger('agent_queries')->default(0)->comment('Agent模式查询次数');
            $table->timestamps();
            
            // 外键约束
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // 唯一索引：每个用户每天只有一条记录
            $table->unique(['user_id', 'date'], 'unique_user_date');
            
            // 日期索引：用于每日重置任务
            $table->index('date', 'idx_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_stats');
    }
};
