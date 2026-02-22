<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为训练计划动作表添加 day_of_week 字段
 * 支持用户自建计划的周视图排列
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_plan_exercises', function (Blueprint $table) {
            $table->tinyInteger('day_of_week')->nullable()
                ->after('exercise_name')
                ->comment('星期几(1=周一..7=周日)，null表示不按天分组');
        });
    }

    public function down(): void
    {
        Schema::table('training_plan_exercises', function (Blueprint $table) {
            $table->dropColumn('day_of_week');
        });
    }
};
