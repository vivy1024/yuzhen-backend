<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 训练计划表
 * 
 * 与前端 training-plan.ts 类型对应：TrainingPlan
 * 支持周期化训练、重量进度等完整参数
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('name')->comment('计划名称');
            $table->string('name_zh')->nullable()->comment('计划名称（中文）');
            $table->text('description')->nullable()->comment('计划描述');
            $table->enum('goal', ['lose_weight', 'gain_muscle', 'maintain', 'improve_fitness'])->nullable()->comment('训练目标');
            $table->enum('difficulty', ['novice', 'beginner', 'intermediate', 'advanced'])->nullable()->comment('难度');
            $table->integer('duration_weeks')->default(4)->comment('总周数');
            $table->integer('workouts_per_week')->default(3)->comment('每周训练次数');
            $table->boolean('is_active')->default(true)->comment('是否激活');
            $table->datetime('started_at')->nullable()->comment('开始时间');
            $table->datetime('completed_at')->nullable()->comment('完成时间');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_active']);
            $table->index('goal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_plans');
    }
};



































