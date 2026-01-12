<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 额度变更日志表 - 会员自动化控制系统
 * 
 * 记录管理员为用户添加额度的操作日志
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 8.3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->integer('dag_amount')->default(0)->comment('DAG额度变更量（正数增加，负数扣减）');
            $table->integer('agent_amount')->default(0)->comment('Agent额度变更量（正数增加，负数扣减）');
            $table->string('reason', 255)->comment('变更原因');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('操作管理员ID');
            $table->timestamp('created_at')->useCurrent()->comment('创建时间');
            
            // 外键约束
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
            
            // 索引
            $table->index('user_id', 'idx_user_id');
            $table->index('created_at', 'idx_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_logs');
    }
};
