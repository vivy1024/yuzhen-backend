<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 积分系统 v2 — Kiro-style credits
 * 
 * 新建表，不修改旧表（旧表标记 DEPRECATED 保留数据）
 */
return new class extends Migration
{
    public function up(): void
    {
        // 用户积分账户（替代旧 user_credits）
        Schema::create('user_credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('balance', 12, 6)->default(100.000000)->comment('当前可用余额');
            $table->decimal('monthly_limit', 12, 6)->default(100.000000)->comment('月度配额');
            $table->decimal('used_this_month', 12, 6)->default(0)->comment('本月已用');
            $table->decimal('bonus_balance', 12, 6)->default(0)->comment('奖励余额（不重置）');
            $table->enum('tier', ['free', 'warmheart', 'energy'])->default('free');
            $table->timestamp('last_reset_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 积分流水（替代旧 credit_transactions）
        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('type', ['earn', 'spend', 'reset', 'bonus', 'admin_adjust']);
            $table->decimal('amount', 12, 6)->comment('变动量（正=获得，负=消耗）');
            $table->decimal('balance_after', 12, 6)->comment('变动后余额');
            $table->string('model', 100)->nullable()->comment('使用的模型（spend 时）');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('session_id', 100)->nullable();
            $table->string('source', 50)->nullable()->comment('来源：register/checkin/invite/admin/ai_chat');
            $table->string('description', 255)->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type']);
        });

        // 模型定价表
        Schema::create('model_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('model_name', 100)->unique();
            $table->decimal('input_price_per_ktoken', 10, 6)->default(0)->comment('输入价格 credits/千token');
            $table->decimal('output_price_per_ktoken', 10, 6)->default(0)->comment('输出价格 credits/千token');
            $table->decimal('real_input_cost_usd', 10, 8)->default(0)->comment('实际输入成本 USD/千token');
            $table->decimal('real_output_cost_usd', 10, 8)->default(0)->comment('实际输出成本 USD/千token');
            $table->enum('cost_tier', ['free', 'low', 'free_quota', 'baseline'])->default('free');
            $table->boolean('enabled')->default(true);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // AI 请求日志（管理员监控用）
        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('session_id', 100)->nullable();
            $table->string('model', 100);
            $table->string('provider', 50)->nullable();
            $table->string('api_key_id', 50)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_creation_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->decimal('real_cost_usd', 12, 8)->default(0);
            $table->decimal('credits_cost', 12, 6)->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('first_token_ms')->nullable();
            $table->enum('status', ['success', 'error', 'timeout'])->default('success');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['model', 'created_at']);
            $table->index('created_at');
        });

        // API Key 状态监控
        Schema::create('api_key_status', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('key_name', 100);
            $table->string('key_hash', 64);
            $table->decimal('balance', 12, 4)->nullable();
            $table->string('balance_unit', 10)->default('USD');
            $table->decimal('total_limit', 12, 4)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->enum('status', ['active', 'low_balance', 'exhausted', 'error'])->default('active');
            $table->decimal('alert_threshold', 12, 4)->default(1.0);
            $table->timestamps();

            $table->unique(['provider', 'key_hash']);
        });

        // 每日签到
        Schema::create('daily_checkins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('checkin_date');
            $table->unsignedInteger('streak_days')->default(1);
            $table->decimal('credits_earned', 12, 6);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'checkin_date']);
        });

        // Seed 模型定价数据
        \DB::table('model_pricing')->insert([
            ['model_name' => 'glm-4.7-flash', 'input_price_per_ktoken' => 0, 'output_price_per_ktoken' => 0, 'real_input_cost_usd' => 0, 'real_output_cost_usd' => 0, 'cost_tier' => 'free', 'enabled' => true],
            ['model_name' => 'Qwen/Qwen3-8B', 'input_price_per_ktoken' => 0, 'output_price_per_ktoken' => 0, 'real_input_cost_usd' => 0, 'real_output_cost_usd' => 0, 'cost_tier' => 'free', 'enabled' => true],
            ['model_name' => 'THUDM/GLM-Z1-9B-0414', 'input_price_per_ktoken' => 0, 'output_price_per_ktoken' => 0, 'real_input_cost_usd' => 0, 'real_output_cost_usd' => 0, 'cost_tier' => 'free', 'enabled' => true],
            ['model_name' => 'deepseek-ai/DeepSeek-R1-Distill-Qwen-7B', 'input_price_per_ktoken' => 0, 'output_price_per_ktoken' => 0, 'real_input_cost_usd' => 0, 'real_output_cost_usd' => 0, 'cost_tier' => 'free', 'enabled' => true],
            ['model_name' => 'deepseek-v4-flash', 'input_price_per_ktoken' => 0.01, 'output_price_per_ktoken' => 0.03, 'real_input_cost_usd' => 0.00000027, 'real_output_cost_usd' => 0.0000011, 'cost_tier' => 'low', 'enabled' => true],
            ['model_name' => 'qwen-turbo', 'input_price_per_ktoken' => 0.01, 'output_price_per_ktoken' => 0.03, 'real_input_cost_usd' => 0.0000003, 'real_output_cost_usd' => 0.0000006, 'cost_tier' => 'free_quota', 'enabled' => true],
            ['model_name' => 'qwen-plus', 'input_price_per_ktoken' => 0.02, 'output_price_per_ktoken' => 0.06, 'real_input_cost_usd' => 0.0000008, 'real_output_cost_usd' => 0.000002, 'cost_tier' => 'free_quota', 'enabled' => true],
            ['model_name' => 'kimi-k2-0905-preview', 'input_price_per_ktoken' => 0.05, 'output_price_per_ktoken' => 0.15, 'real_input_cost_usd' => 0.000002, 'real_output_cost_usd' => 0.000006, 'cost_tier' => 'baseline', 'enabled' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_checkins');
        Schema::dropIfExists('api_key_status');
        Schema::dropIfExists('ai_request_logs');
        Schema::dropIfExists('model_pricing');
        Schema::dropIfExists('credit_ledger');
        Schema::dropIfExists('user_credit_accounts');
    }
};
