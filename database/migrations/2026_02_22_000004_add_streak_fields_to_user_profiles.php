<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为用户档案添加连续训练天数和总训练天数字段
 * 支持成就徽章和留存机制
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->unsignedInteger('streak_days')->default(0)
                ->after('ffmi_assessment')
                ->comment('连续训练天数');
            $table->unsignedInteger('total_training_days')->default(0)
                ->after('streak_days')
                ->comment('累计训练天数');
            $table->date('last_training_date')->nullable()
                ->after('total_training_days')
                ->comment('最近一次训练日期');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['streak_days', 'total_training_days', 'last_training_date']);
        });
    }
};
