<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建chat_messages表
 * 存储AI聊天的用户消息和AI回复
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('topic_id')->comment('话题ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->enum('role', ['user', 'assistant', 'system'])->comment('消息角色');
            $table->text('content')->comment('消息内容');
            $table->json('metadata')->nullable()->comment('元数据（工具调用、训练计划等）');
            $table->string('client_id', 64)->nullable()->comment('客户端消息ID，用于去重');
            $table->timestamps();
            
            // 索引
            $table->index('topic_id');
            $table->index('user_id');
            $table->index(['topic_id', 'created_at']);
            $table->unique('client_id');
            
            // 外键
            $table->foreign('topic_id')->references('id')->on('chat_topics')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
