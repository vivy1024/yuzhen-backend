<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 给 training_logs 添加 status 和 completed_at 列
 *
 * 修复数据源不一致问题：TrainingLogController::complete() 依赖这两列，
 * 但原始 migration 未定义。同时统一日历数据源从 training_sessions → training_logs。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_logs', function (Blueprint $table) {
            $table->enum('status', ['draft', 'in_progress', 'completed'])
                ->default('draft')
                ->after('notes')
                ->comment('训练状态');

            $table->timestamp('completed_at')
                ->nullable()
                ->after('status')
                ->comment('完成时间');

            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index('completed_at', 'idx_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('training_logs', function (Blueprint $table) {
            $table->dropIndex('idx_user_status');
            $table->dropIndex('idx_completed_at');
            $table->dropColumn(['status', 'completed_at']);
        });
    }
};
