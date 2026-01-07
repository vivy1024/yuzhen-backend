<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 训练计划动作表
 * 
 * 存储AI生成的训练计划中的预设动作
 * 与training_records不同，这是计划模板中的动作，而非实际训练记录
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_plan_exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->comment('训练计划ID');
            $table->unsignedBigInteger('exercise_id')->nullable()->comment('动作ID（可能为空，如果是未识别的动作）');
            $table->string('exercise_name')->comment('动作名称');
            $table->integer('sets')->default(3)->comment('组数');
            $table->string('reps')->default('8-12')->comment('次数范围');
            $table->string('weight')->nullable()->comment('建议重量');
            $table->string('rest_time')->default('60s')->comment('组间休息');
            $table->text('notes')->nullable()->comment('备注');
            $table->integer('order_index')->default(0)->comment('排序索引');
            $table->timestamps();
            
            $table->foreign('plan_id')->references('id')->on('training_plans')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('set null');
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_plan_exercises');
    }
};
