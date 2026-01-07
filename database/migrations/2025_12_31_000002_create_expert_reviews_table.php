<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 专家评审表
 * 
 * 存储专家对AI对话的专业评分（6维度）：
 * 1. 专业准确性：健身知识是否准确
 * 2. 科学合理性：建议是否符合运动科学
 * 3. 安全性：建议是否安全（一票否决）
 * 4. 完整性：回答是否完整全面
 * 5. 实用性：建议是否可执行
 * 6. 个性化适配度：是否针对用户情况定制
 * 
 * Requirements: 7.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_session_id')
                ->comment('关联的对话会话ID');
            $table->unsignedBigInteger('expert_id')
                ->comment('评审专家的用户ID');
            
            // ========== 专家评分6维度（1-5分） ==========
            $table->tinyInteger('accuracy')
                ->comment('专业准确性（1-5）：健身知识是否准确');
            $table->tinyInteger('scientific')
                ->comment('科学合理性（1-5）：建议是否符合运动科学');
            $table->tinyInteger('safety')
                ->comment('安全性（1-5）：建议是否安全，<3则一票否决');
            $table->tinyInteger('completeness')
                ->comment('完整性（1-5）：回答是否完整全面');
            $table->tinyInteger('practicality')
                ->comment('实用性（1-5）：建议是否可执行');
            $table->tinyInteger('personalization')
                ->comment('个性化适配度（1-5）：是否针对用户情况定制');
            
            // ========== 评审意见 ==========
            $table->text('comments')->nullable()
                ->comment('评审意见和建议');
            $table->json('improvement_suggestions')->nullable()
                ->comment('改进建议（JSON数组）');
            
            // ========== 时间戳 ==========
            $table->timestamp('reviewed_at')->useCurrent()
                ->comment('评审时间');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()
                ->comment('更新时间');
            
            // ========== 外键和索引 ==========
            $table->foreign('chat_session_id')
                ->references('id')
                ->on('chat_sessions')
                ->onDelete('cascade');
            $table->foreign('expert_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            
            $table->index('chat_session_id', 'idx_expert_reviews_session');
            $table->index('expert_id', 'idx_expert_reviews_expert');
            $table->index('safety', 'idx_expert_reviews_safety');
            $table->index('reviewed_at', 'idx_expert_reviews_time');
            
            // 确保每个专家对每个会话只能评审一次
            $table->unique(['chat_session_id', 'expert_id'], 'uk_session_expert');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_reviews');
    }
};
