<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 添加 training_feedback 字段到 user_profiles 表
 * 
 * 用于记录用户的训练反馈：
 * - 疲劳程度（1-10分）
 * - 主观感受（文本描述）
 * - 训练记录（动作、组数、次数、重量等）
 * 
 * 数据结构示例：
 * {
 *   "session_id": "session_123",
 *   "date": "2025-12-20T10:00:00Z",
 *   "fatigue_level": 7,
 *   "subjective_feeling": "感觉不错，力量有提升",
 *   "training_records": [
 *     {
 *       "exercise_name": "深蹲",
 *       "sets": 4,
 *       "reps": 8,
 *       "weight": 100,
 *       "notes": "最后一组有点吃力"
 *     }
 *   ]
 * }
 * 
 * @version 1.0.0
 * @date 2025-12-20
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // 添加训练反馈字段（JSON格式）
            $table->json('training_feedback')
                ->nullable()
                ->after('strength_progress')
                ->comment('训练反馈记录（疲劳程度、主观感受、训练记录）');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('training_feedback');
        });
    }
};
