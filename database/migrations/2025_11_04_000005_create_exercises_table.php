<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 动作库主表
 * 
 * 与前端 exercise.ts 类型对应：ExerciseDetail
 * 字段命名与后端API响应保持一致（snake_case）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            
            // 基础信息
            $table->string('name')->comment('动作名称（英文）');
            $table->string('name_zh')->nullable()->comment('动作名称（中文）');
            $table->string('slug')->unique()->comment('URL友好标识');
            $table->text('description')->nullable()->comment('描述（英文）');
            $table->text('description_zh')->nullable()->comment('描述（中文）');
            
            // 动作要领（JSON数组）
            $table->json('correct_steps')->nullable()->comment('正确步骤（英文）');
            $table->json('correct_steps_zh')->nullable()->comment('正确步骤（中文）');
            
            // 肌群相关
            $table->string('primary_muscle')->nullable()->comment('主要肌群（英文）');
            $table->json('secondary_muscles')->nullable()->comment('次要肌群（英文数组）');
            
            // 器械和难度
            $table->string('equipment')->nullable()->comment('所需器械（英文）');
            $table->string('difficulty', 50)->nullable()->comment('难度等级');
            
            // 动作类型
            $table->string('force_type', 50)->nullable()->comment('力学类型：push/pull/static');
            $table->string('mechanic_type', 50)->nullable()->comment('动作类型：compound/isolation');
            
            // 分类和标签（JSON数组）
            $table->json('grips')->nullable()->comment('握法列表');
            $table->json('categories')->nullable()->comment('分类列表');
            $table->json('smart_tags')->nullable()->comment('智能标签');
            
            // 统计数据
            $table->integer('rating')->default(0)->comment('评分');
            $table->integer('view_count')->default(0)->comment('浏览次数');
            
            $table->timestamps();
            
            // 索引
            $table->index('primary_muscle');
            $table->index('equipment');
            $table->index('difficulty');
            $table->index('view_count');
            
            // 全文搜索索引
            $table->fullText(['name', 'name_zh', 'description', 'description_zh']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};



































