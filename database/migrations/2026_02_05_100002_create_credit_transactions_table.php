<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建credit_transactions表 - 积分流水记录
 * 
 * 记录每次AI对话的积分消耗详情，包括：
 * - 消耗的积分数量和Token数量
 * - 查询模式（DAG/Agent）
 * - DAG模板名称
 * - 关联的会话ID
 * - 输入/输出Token明细
 * 
 * 积分计算公式：credits = ceil(tokens × multiplier / 1000)
 * - DAG模式：multiplier = 1.0
 * - Agent模式：multiplier = 1.5
 * - 最小消耗：1积分
 * 
 * @version v1.0.0
 * @date 2026-02-05
 * @author 薛小川
 * @requirements 9.2, 9.4, 9.5
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            
            // 用户关联
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            
            // 积分消耗信息
            $table->integer('credits')->comment('消耗积分数（正数为消耗，负数为充值/分享获得）');
            $table->integer('tokens')->comment('消耗Token总数');
            
            // 查询模式
            $table->enum('mode', ['dag', 'agent'])->comment('查询模式：dag=DAG模式(1.0x), agent=Agent模式(1.5x)');
            
            // DAG模板信息
            $table->string('template_name', 100)->nullable()->comment('DAG模板名称');
            
            // 会话关联
            $table->string('conversation_id', 100)->nullable()->comment('会话ID，用于关联具体对话');
            
            // Token明细
            $table->integer('input_tokens')->default(0)->comment('输入Token数');
            $table->integer('output_tokens')->default(0)->comment('输出Token数');
            
            // 描述信息
            $table->string('description', 255)->nullable()->comment('交易描述');
            
            // 时间戳（只需要created_at，流水记录不可修改）
            $table->timestamp('created_at')->useCurrent()->comment('创建时间');
            
            // 外键约束
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            // 索引优化
            // 1. 用户ID索引 - 查询用户的积分流水
            $table->index('user_id', 'idx_credit_trans_user_id');
            
            // 2. 创建时间索引 - 按时间范围查询
            $table->index('created_at', 'idx_credit_trans_created_at');
            
            // 3. 会话ID索引 - 查询特定会话的积分消耗
            $table->index('conversation_id', 'idx_credit_trans_conversation');
            
            // 4. 复合索引 - 用户+时间，用于统计用户某时间段的消耗
            $table->index(['user_id', 'created_at'], 'idx_credit_trans_user_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
