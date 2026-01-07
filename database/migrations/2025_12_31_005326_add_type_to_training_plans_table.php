<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 添加type字段用于标记训练计划来源（AI生成或手动创建）
     */
    public function up(): void
    {
        Schema::table('training_plans', function (Blueprint $table) {
            // 添加type字段，用于区分AI生成和手动创建的计划
            // ai_generated: AI智能生成
            // manual: 手动创建
            $table->string('type', 50)->default('manual')->after('is_active')
                ->comment('计划来源类型: ai_generated=智能生成, manual=手动创建');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_plans', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
