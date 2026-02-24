<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 训练计划模板表
 * 预置官方模板，用户可从模板创建个人计划
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('模板名称');
            $table->text('description')->nullable()->comment('模板描述');
            $table->enum('goal', ['lose_weight', 'gain_muscle', 'maintain', 'improve_fitness'])->comment('训练目标');
            $table->enum('level', ['novice', 'beginner', 'intermediate', 'advanced'])->comment('适合等级');
            $table->integer('duration_weeks')->default(4)->comment('周期(周)');
            $table->integer('workouts_per_week')->default(3)->comment('每星期训练次数');
            $table->json('exercises')->comment('动作列表JSON');
            $table->json('tags')->nullable()->comment('标签');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->integer('use_count')->default(0)->comment('使用次数');
            $table->timestamps();

            $table->index(['goal', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_templates');
    }
};
