<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 对话会话表
 * 
 * 混合存储架构：MySQL（结构化数据） + Qdrant（向量检索）
 * 支持：
 * - 多轮对话追踪（session_id）
 * - 匿名用户对话（user_id可为NULL）
 * - Few-Shot学习（从Qdrant检索历史对话）
 * - 质量评估（user_rating, user_feedback）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('session_id', 36)->comment('会话UUID，支持多轮对话');
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID（匿名用户为NULL）');
            $table->text('user_query')->comment('用户问题');
            $table->text('llm_response')->comment('AI回答');
            $table->string('model_used', 50)->comment('使用的模型（deepseek-chat/ollama-qwen3:8b）');
            $table->json('tools_used')->nullable()->comment('调用的工具列表（JSON数组）');
            $table->json('metadata')->nullable()->comment('元数据：few_shot_count, orchestrator_used等');
            $table->tinyInteger('user_rating')->nullable()->comment('用户评分（1-5星）');
            $table->string('user_feedback', 500)->nullable()->comment('用户反馈');
            $table->char('qdrant_point_id', 36)->nullable()->comment('Qdrant向量点ID');
            $table->timestamp('created_at')->useCurrent()->comment('创建时间');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新时间');
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('session_id', 'idx_session_id');
            $table->index('user_id', 'idx_user_id');
            $table->index('created_at', 'idx_created_at');
            $table->index('model_used', 'idx_model_used');
            $table->index('qdrant_point_id', 'idx_qdrant_point_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};

































