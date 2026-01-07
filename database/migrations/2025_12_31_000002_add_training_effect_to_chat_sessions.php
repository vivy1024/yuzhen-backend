<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 添加训练效果标签字段到chat_sessions表
 * 
 * 支持Few-Shot系统的训练效果标签功能
 * @requirements 4.4
 * 
 * @version 1.0.0
 * @date 2025-12-31
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            // 训练效果标签：excellent/good/fair/poor
            $table->enum('training_effect', ['excellent', 'good', 'fair', 'poor'])
                ->nullable()
                ->comment('训练效果标签：excellent(优秀)/good(良好)/fair(一般)/poor(较差)')
                ->after('fewshot_eligible');
            
            // 添加索引
            $table->index('training_effect', 'idx_training_effect');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_training_effect');
            $table->dropColumn('training_effect');
        });
    }
};
