<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name', 100);  // 话题名称
            $table->text('description')->nullable();  // 话题描述
            $table->integer('message_count')->default(0);  // 消息数量
            $table->text('last_message')->nullable();  // 最后一条消息
            $table->timestamp('last_message_at')->nullable();  // 最后消息时间
            $table->timestamps();
            $table->softDeletes();  // 软删除
            
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_topics');
    }
};
