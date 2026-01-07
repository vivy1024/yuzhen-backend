<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建 training_logs 表 - 训练日志表
 * 
 * 用于闭环学习系统，记录用户每次训练的详细数据：
 * - 训练日期
 * - 计划动作列表（JSON）
 * - 实际完成情况（JSON）
 * - 完成率
 * - 平均RPE
 * 
 * 数据结构示例：
 * planned_exercises: [
 *   {"exercise_id": "ex_001", "name": "深蹲", "sets": 4, "reps": 8, "weight": 100}
 * ]
 * actual_exercises: [
 *   {"exercise_id": "ex_001", "name": "深蹲", "completed_sets": 4, "completed_reps": [8,8,7,6], "actual_weight": 100, "rpe": 8}
 * ]
 * 
 * @version 1.0.0
 * @date 2025-12-26
 * @requirements 6.1, 6.2, 6.3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_logs', function (Blueprint $table) {
            $table->id();
            
            // 用户关联
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('用户ID');
            
            // 训练日期
            $table->date('session_date')
                ->comment('训练日期');
            
            // 计划动作列表（JSON）
            $table->json('planned_exercises')
                ->nullable()
                ->comment('计划动作列表');
            
            // 实际完成情况（JSON）
            $table->json('actual_exercises')
                ->nullable()
                ->comment('实际完成情况');
            
            // 完成率（0-1）
            $table->decimal('completion_rate', 3, 2)
                ->nullable()
                ->comment('完成率（0-1）');
            
            // 平均RPE（1-10）
            $table->decimal('avg_rpe', 3, 1)
                ->nullable()
                ->comment('平均RPE（1-10）');
            
            // 训练周期信息
            $table->integer('week_number')
                ->nullable()
                ->comment('周期内第几周');
            
            $table->string('mesocycle_id', 50)
                ->nullable()
                ->comment('中周期ID');
            
            // 备注
            $table->text('notes')
                ->nullable()
                ->comment('备注');
            
            $table->timestamps();
            
            // 索引
            $table->index(['user_id', 'session_date'], 'idx_user_date');
            $table->index('session_date', 'idx_session_date');
            $table->index('mesocycle_id', 'idx_mesocycle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_logs');
    }
};
