<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 添加 strength_progress 字段到 user_profiles 表
 * 
 * 用于记录用户的力量进步曲线：
 * - 每个动作的历史最大重量和日期
 * - 估算的当前1RM
 * - 动态评估的力量水平
 * 
 * 数据结构示例：
 * {
 *   "squat": {
 *     "history": [
 *       {"weight": 80, "reps": 5, "date": "2025-01-01", "estimated_1rm": 90},
 *       {"weight": 85, "reps": 5, "date": "2025-01-15", "estimated_1rm": 96}
 *     ],
 *     "current_1rm": 96,
 *     "strength_level": "intermediate",
 *     "last_updated": "2025-01-15"
 *   },
 *   "bench_press": {
 *     "history": [...],
 *     "current_1rm": 75,
 *     "strength_level": "novice",
 *     "last_updated": "2025-01-15"
 *   }
 * }
 * 
 * @version 1.0.0
 * @date 2025-12-19
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // 添加力量进步曲线字段（JSON格式）
            $table->json('strength_progress')
                ->nullable()
                ->after('strength_data')
                ->comment('力量进步曲线：记录每个动作的历史最大重量、估算1RM和力量水平');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('strength_progress');
        });
    }
};
