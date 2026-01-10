<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 用户用量统计表
 * 
 * 用于追踪用户的AI对话次数（DAG模式和Agent模式分开统计）
 * 支持打赏后管理员手动添加额外次数
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_usage_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->date('date')->comment('统计日期');
            
            // DAG模式统计
            $table->unsignedInteger('dag_queries')->default(0)->comment('DAG模式查询次数');
            $table->unsignedInteger('dag_limit')->default(10)->comment('DAG模式每日限制（-1表示无限）');
            
            // Agent模式统计
            $table->unsignedInteger('agent_queries')->default(0)->comment('Agent模式查询次数');
            $table->unsignedInteger('agent_limit')->default(3)->comment('Agent模式每日限制（-1表示无限）');
            
            // 额外次数（打赏奖励）
            $table->unsignedInteger('bonus_dag_queries')->default(0)->comment('额外DAG次数（打赏奖励）');
            $table->unsignedInteger('bonus_agent_queries')->default(0)->comment('额外Agent次数（打赏奖励）');
            
            // 元数据
            $table->json('metadata')->nullable()->comment('元数据（工具调用统计等）');
            
            $table->timestamps();
            
            // 索引
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'date'], 'idx_user_date');
            $table->index('date', 'idx_date');
        });
        
        // 用户总额外次数表（打赏累计）
        Schema::create('user_bonus_credits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->comment('用户ID');
            
            // 累计额外次数
            $table->unsignedInteger('total_dag_credits')->default(0)->comment('累计DAG额外次数');
            $table->unsignedInteger('total_agent_credits')->default(0)->comment('累计Agent额外次数');
            
            // 已使用次数
            $table->unsignedInteger('used_dag_credits')->default(0)->comment('已使用DAG额外次数');
            $table->unsignedInteger('used_agent_credits')->default(0)->comment('已使用Agent额外次数');
            
            // 打赏记录
            $table->json('donation_history')->nullable()->comment('打赏历史记录');
            
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bonus_credits');
        Schema::dropIfExists('user_usage_stats');
    }
};
