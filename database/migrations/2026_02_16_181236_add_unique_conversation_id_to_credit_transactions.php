<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为 credit_transactions 表添加 conversation_id 幂等性唯一索引
 *
 * 防止 DAML-RAG 重试导致同一会话重复扣积分
 * 使用 user_id + conversation_id 复合唯一索引（不同用户可能有相同 conversation_id）
 * conversation_id 为 nullable，MySQL 中 NULL 值不参与唯一约束
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            // 删除旧的普通索引
            $table->dropIndex('idx_credit_trans_conversation');
            // 添加复合唯一索引
            $table->unique(['user_id', 'conversation_id'], 'idx_credit_trans_user_conversation_unique');
        });
    }

    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropUnique('idx_credit_trans_user_conversation_unique');
            $table->index('conversation_id', 'idx_credit_trans_conversation');
        });
    }
};
