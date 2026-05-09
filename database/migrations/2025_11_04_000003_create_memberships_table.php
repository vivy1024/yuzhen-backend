<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('等级名称');
            $table->string('slug', 50)->unique()->comment('唯一标识');
            $table->string('tier', 50)->default('free')->comment('等级标识：free/warmheart/energy');
            $table->decimal('price', 10, 2)->default(0)->comment('价格（元）');
            $table->integer('duration_days')->comment('有效天数');
            $table->integer('max_training_plans')->default(3)->comment('最大训练计划数量');
            $table->boolean('unlock_all_exercises')->default(false)->comment('解锁所有动作');
            $table->boolean('ai_recommendation')->default(false)->comment('AI推荐功能');
            $table->boolean('data_analysis')->default(false)->comment('数据分析功能');
            $table->boolean('coach_service')->default(false)->comment('教练服务');
            $table->text('description')->nullable()->comment('等级描述');
            $table->json('features')->nullable()->comment('功能列表');
            $table->json('limits')->nullable()->comment('限制说明');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
            
            $table->index('slug');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};



































