<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 扩展chat_sessions表，添加三轨评分字段
 * 
 * 三轨评分体系：
 * 1. 用户体验评分（5维度）：易懂性、实用性、详细程度、友好度、整体满意度
 * 2. 个性化感知评分（4维度）：档案利用率、目标对齐度、独特性、动态调整
 * 3. 综合评分（3字段）：个性化等级、Few-Shot资格、综合评分
 * 
 * Requirements: 7.1, 7.3, 7.4, 7.5
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            // ========== 用户体验评分（5维度，1-5分） ==========
            $table->tinyInteger('ux_clarity')->nullable()
                ->comment('易懂性评分（1-5）：回复是否容易理解')
                ->after('user_feedback');
            $table->tinyInteger('ux_practicality')->nullable()
                ->comment('实用性评分（1-5）：建议是否实用可行')
                ->after('ux_clarity');
            $table->tinyInteger('ux_detail')->nullable()
                ->comment('详细程度评分（1-5）：信息是否足够详细')
                ->after('ux_practicality');
            $table->tinyInteger('ux_friendliness')->nullable()
                ->comment('友好度评分（1-5）：回复语气是否友好')
                ->after('ux_detail');
            $table->tinyInteger('ux_satisfaction')->nullable()
                ->comment('整体满意度评分（1-5）：用户整体满意程度')
                ->after('ux_friendliness');
            
            // ========== 个性化感知评分（4维度，0-100%） ==========
            $table->decimal('profile_utilization_rate', 5, 2)->nullable()
                ->comment('档案利用率（0-100%）：系统对用户档案的使用程度')
                ->after('ux_satisfaction');
            $table->decimal('goal_alignment', 5, 2)->nullable()
                ->comment('目标对齐度（0-100%）：建议与用户目标的匹配程度')
                ->after('profile_utilization_rate');
            $table->decimal('uniqueness', 5, 2)->nullable()
                ->comment('独特性（0-100%）：回复的个性化程度')
                ->after('goal_alignment');
            $table->decimal('dynamic_adjustment', 5, 2)->nullable()
                ->comment('动态调整（0-100%）：根据用户反馈调整的程度')
                ->after('uniqueness');
            
            // ========== 综合评分（3字段） ==========
            $table->enum('personalization_grade', ['S', 'A', 'B', 'C', 'D'])->nullable()
                ->comment('个性化等级：S(90-100%), A(75-89%), B(60-74%), C(40-59%), D(0-39%)')
                ->after('dynamic_adjustment');
            $table->boolean('fewshot_eligible')->default(false)
                ->comment('是否符合Few-Shot条件：三轨高分(≥4.0)且安全性≥3')
                ->after('personalization_grade');
            $table->decimal('overall_score', 3, 2)->nullable()
                ->comment('综合评分（0-5）：三轨评分的加权平均')
                ->after('fewshot_eligible');
            
            // ========== 索引优化 ==========
            $table->index('personalization_grade', 'idx_personalization_grade');
            $table->index('fewshot_eligible', 'idx_fewshot_eligible');
            $table->index('overall_score', 'idx_overall_score');
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            // 删除索引
            $table->dropIndex('idx_personalization_grade');
            $table->dropIndex('idx_fewshot_eligible');
            $table->dropIndex('idx_overall_score');
            
            // 删除综合评分字段
            $table->dropColumn('overall_score');
            $table->dropColumn('fewshot_eligible');
            $table->dropColumn('personalization_grade');
            
            // 删除个性化感知评分字段
            $table->dropColumn('dynamic_adjustment');
            $table->dropColumn('uniqueness');
            $table->dropColumn('goal_alignment');
            $table->dropColumn('profile_utilization_rate');
            
            // 删除用户体验评分字段
            $table->dropColumn('ux_satisfaction');
            $table->dropColumn('ux_friendliness');
            $table->dropColumn('ux_detail');
            $table->dropColumn('ux_practicality');
            $table->dropColumn('ux_clarity');
        });
    }
};
