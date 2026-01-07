<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 为现有training_plans表添加AI聊天相关字段
     */
    public function up(): void
    {
        Schema::table('training_plans', function (Blueprint $table) {
            // 添加来源对话关联
            $table->foreignId('chat_session_id')->nullable()
                  ->after('user_id')
                  ->constrained()->onDelete('set null')
                  ->comment('来源对话ID');
            
            // 添加AI生成的详细数据字段
            $table->json('exercises')->nullable()
                  ->after('workouts_per_week')
                  ->comment('动作列表（AI生成）');
            
            $table->json('target_muscles')->nullable()
                  ->after('exercises')
                  ->comment('目标肌群列表');
            
            $table->json('safety_notes')->nullable()
                  ->after('target_muscles')
                  ->comment('安全提示列表');
            
            // 添加软删除
            $table->softDeletes()->after('updated_at');
            
            // 添加索引
            $table->index(['chat_session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_plans', function (Blueprint $table) {
            $table->dropForeign(['chat_session_id']);
            $table->dropIndex(['chat_session_id']);
            $table->dropColumn([
                'chat_session_id',
                'exercises',
                'target_muscles',
                'safety_notes',
                'deleted_at'
            ]);
        });
    }
};
