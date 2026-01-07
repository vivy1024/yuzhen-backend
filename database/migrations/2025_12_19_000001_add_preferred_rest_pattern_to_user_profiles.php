<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 添加 preferred_rest_pattern 字段到 user_profiles 表
 * 
 * 支持用户自主选择休息模式：
 * - 练一休一
 * - 练二休一
 * - 练三休一
 * - 练四休一
 * - 练五休一
 * - 练六休一
 * - 练七休一
 * 
 * @version 1.0.0
 * @date 2025-12-19
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('preferred_rest_pattern', 50)
                ->nullable()
                ->after('training_preferences')
                ->comment('首选休息模式（练一休一、练二休一等）');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('preferred_rest_pattern');
        });
    }
};
