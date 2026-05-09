<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建 chat_threads 表
 * 
 * 存储 AI 对话线程，对应 DAML-RAG v2 的 LangGraph thread_id。
 * 每个 thread 代表一个持久化的对话上下文。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_threads', function (Blueprint $table) {
            $table->id();
            $table->string('thread_id', 128)->unique()->comment('LangGraph thread ID');
            $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
            $table->string('title', 200)->default('新对话')->comment('线程标题');
            $table->string('status', 20)->default('active')->comment('状态: active/archived');
            $table->string('last_skill', 64)->nullable()->comment('最后使用的 Skill ID');
            $table->unsignedInteger('message_count')->default(0)->comment('消息数量');
            $table->timestamps();

            $table->index(['user_id', 'status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_threads');
    }
};
