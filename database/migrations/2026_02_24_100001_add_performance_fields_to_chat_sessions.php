<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 扩展 chat_sessions 表，添加性能监控字段
 *
 * 用于统一可观测性仪表盘（unified-observability-dashboard），
 * 将 Prometheus 内存中的易失性指标持久化到 MySQL，支持：
 * - 性能指标：TTFB、总耗时、Token生成速率
 * - 模型/模式：实际后端、执行模式、模板名称
 * - Token/费用：输入输出Token、估算费用、消耗积分
 * - 降级/错误：降级次数、错误类型
 *
 * 数据来源：stream_executor → credit_reporter → InternalCreditController
 *
 * @date 2026-02-24
 * @author 薛小川
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            // ========== 性能指标 ==========
            $table->unsignedInteger('ttfb_ms')->nullable()
                ->comment('首字节时间(毫秒)')
                ->after('training_effect');
            $table->unsignedInteger('duration_ms')->nullable()
                ->comment('总耗时(毫秒)')
                ->after('ttfb_ms');
            $table->decimal('tokens_per_sec', 6, 2)->nullable()
                ->comment('令牌生成速率(tokens/s)')
                ->after('duration_ms');

            // ========== 模型/模式 ==========
            $table->string('backend_used', 50)->nullable()
                ->comment('实际后端: anthropic/deepseek/glm/siliconflow')
                ->after('tokens_per_sec');
            $table->enum('execution_mode', ['dag', 'agent'])->default('dag')
                ->comment('执行模式: dag=DAG固定编排, agent=Agent动态决策')
                ->after('backend_used');
            $table->string('template_name', 100)->nullable()
                ->comment('DAG模板名 / Agent skill名')
                ->after('execution_mode');

            // ========== Token/费用 ==========
            $table->unsignedInteger('input_tokens')->default(0)
                ->comment('输入Token数')
                ->after('template_name');
            $table->unsignedInteger('output_tokens')->default(0)
                ->comment('输出Token数')
                ->after('input_tokens');
            $table->decimal('estimated_cost', 8, 4)->nullable()
                ->comment('估算费用(美元)')
                ->after('output_tokens');
            $table->unsignedInteger('credits_consumed')->default(0)
                ->comment('消耗积分')
                ->after('estimated_cost');

            // ========== 降级/错误 ==========
            $table->unsignedTinyInteger('fallback_count')->default(0)
                ->comment('降级次数')
                ->after('credits_consumed');
            $table->string('error_type', 50)->nullable()
                ->comment('错误类型: timeout/4xx/5xx/none')
                ->after('fallback_count');

            // ========== 索引优化 ==========
            $table->index('backend_used', 'idx_cs_backend');
            $table->index('execution_mode', 'idx_cs_mode');
            $table->index(['created_at', 'backend_used'], 'idx_cs_date_backend');
            $table->index(['created_at', 'execution_mode'], 'idx_cs_date_mode');
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            // 删除索引
            $table->dropIndex('idx_cs_backend');
            $table->dropIndex('idx_cs_mode');
            $table->dropIndex('idx_cs_date_backend');
            $table->dropIndex('idx_cs_date_mode');

            // 删除字段
            $table->dropColumn([
                'error_type',
                'fallback_count',
                'credits_consumed',
                'estimated_cost',
                'output_tokens',
                'input_tokens',
                'template_name',
                'execution_mode',
                'backend_used',
                'tokens_per_sec',
                'duration_ms',
                'ttfb_ms',
            ]);
        });
    }
};
