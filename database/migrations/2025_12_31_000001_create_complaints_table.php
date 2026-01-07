<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建用户投诉表
 * 
 * Requirements: 16.5
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            
            // 关联用户
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
            
            // 关联聊天会话（可选）
            $table->foreignId('chat_session_id')
                ->nullable()
                ->constrained()
                ->onDelete('set null');
            
            // 投诉类型
            $table->enum('type', [
                'content_quality',   // 内容质量问题
                'content_safety',    // 内容安全问题
                'technical_error',   // 技术错误
                'inappropriate',     // 不当内容
                'other',             // 其他问题
            ])->default('other');
            
            // 投诉内容
            $table->text('content');
            
            // 截图URL（可选）
            $table->string('screenshot_url')->nullable();
            
            // 投诉状态
            $table->enum('status', [
                'pending',     // 待处理
                'processing',  // 处理中
                'resolved',    // 已解决
                'rejected',    // 已驳回
                'closed',      // 已关闭
            ])->default('pending');
            
            // 处理人ID
            $table->string('handler_id')->nullable();
            
            // 处理回复
            $table->text('handler_response')->nullable();
            
            // 处理时间
            $table->timestamp('handled_at')->nullable();
            
            $table->timestamps();
            
            // 索引
            $table->index('user_id');
            $table->index('status');
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
