<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 用户饮食计划表
 * 关联到 training_plans，一个计划可同时包含训练和饮食安排
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_nutrition_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->comment('训练计划ID');
            $table->unsignedBigInteger('food_id')->nullable()->comment('食物ID');
            $table->string('food_name', 100)->comment('食物名称');
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snack'])->comment('餐次');
            $table->decimal('portion_grams', 6, 1)->default(100)->comment('份量(克)');
            $table->tinyInteger('day_of_week')->nullable()->comment('星期几(1-7)');
            $table->text('notes')->nullable()->comment('备注');
            $table->integer('order_index')->default(0)->comment('排序');
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('training_plans')->onDelete('cascade');
            $table->foreign('food_id')->references('id')->on('foods')->onDelete('set null');
            $table->index(['plan_id', 'day_of_week', 'meal_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_nutrition_plans');
    }
};
