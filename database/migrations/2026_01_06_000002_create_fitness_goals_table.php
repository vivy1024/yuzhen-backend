<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建 fitness_goals 表 - 健身目标表
 * 
 * 用于存储用户的健身目标（体重、体脂、力量等）
 * 支持目标进度追踪和预计完成时间计算
 * 
 * @version 1.0.0
 * @date 2026-01-06
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fitness_goals', function (Blueprint $table) {
            $table->id();
            
            // 用户关联
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('用户ID');
            
            // 目标类型
            $table->enum('type', ['weight', 'body_fat', 'muscle_mass', 'strength', 'custom'])
                ->comment('目标类型');
            
            // 目标名称
            $table->string('name', 100)
                ->comment('目标名称');
            
            // 目标值
            $table->decimal('target_value', 8, 2)
                ->comment('目标值');
            
            // 当前值
            $table->decimal('current_value', 8, 2)
                ->comment('当前值');
            
            // 起始值
            $table->decimal('start_value', 8, 2)
                ->comment('起始值');
            
            // 单位
            $table->string('unit', 20)
                ->comment('单位（kg, %, 次等）');
            
            // 开始日期
            $table->date('start_date')
                ->comment('开始日期');
            
            // 目标日期
            $table->date('target_date')
                ->nullable()
                ->comment('目标日期');
            
            // 完成日期
            $table->date('completed_at')
                ->nullable()
                ->comment('完成日期');
            
            // 状态
            $table->enum('status', ['active', 'completed', 'abandoned'])
                ->default('active')
                ->comment('状态');
            
            // 备注
            $table->text('notes')
                ->nullable()
                ->comment('备注');
            
            $table->timestamps();
            
            // 索引
            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index('type', 'idx_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fitness_goals');
    }
};
