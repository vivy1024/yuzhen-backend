<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 添加训练计划关联字段到训练日志表
 * 
 * 实现训练计划与训练记录的关联
 * Requirements: 5.1 - 导入计划自动关联训练记录模块
 * 
 * @version 1.0.0
 * @date 2025-12-31
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_logs', function (Blueprint $table) {
            // 训练计划关联
            $table->unsignedBigInteger('training_plan_id')
                ->nullable()
                ->after('user_id')
                ->comment('关联的训练计划ID');
            
            // 计划中的周数和天数
            $table->integer('plan_week')
                ->nullable()
                ->after('training_plan_id')
                ->comment('计划中的第几周');
            
            $table->integer('plan_day')
                ->nullable()
                ->after('plan_week')
                ->comment('计划中的第几天');
            
            // 添加外键约束
            $table->foreign('training_plan_id')
                ->references('id')
                ->on('training_plans')
                ->onDelete('set null');
            
            // 添加索引
            $table->index('training_plan_id', 'idx_training_plan');
            $table->index(['training_plan_id', 'plan_week', 'plan_day'], 'idx_plan_schedule');
        });
    }

    public function down(): void
    {
        Schema::table('training_logs', function (Blueprint $table) {
            $table->dropForeign(['training_plan_id']);
            $table->dropIndex('idx_training_plan');
            $table->dropIndex('idx_plan_schedule');
            $table->dropColumn(['training_plan_id', 'plan_week', 'plan_day']);
        });
    }
};
