<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 用户额度表 - 会员自动化控制系统
 * 
 * 存储用户的额外DAG和Agent次数（打赏奖励）
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 8.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_credits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->comment('用户ID');
            $table->unsignedInteger('dag_credits')->default(0)->comment('DAG额外次数');
            $table->unsignedInteger('agent_credits')->default(0)->comment('Agent额外次数');
            $table->timestamp('updated_at')->nullable()->comment('最后更新时间');
            
            // 外键约束
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_credits');
    }
};
