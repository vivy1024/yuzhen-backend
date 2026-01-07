<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 用户档案表（JSON结构）
 * 
 * 与前端 user-profile.ts 类型完全对应：
 * - basic_info: BasicInfo
 * - fitness_goals: FitnessGoals
 * - training_preferences: TrainingPreferences
 * - strength_data: StrengthData
 * - health_status: HealthStatus
 * - nutrition_profile: NutritionProfile
 * - ffmi_assessment: FFMIAssessment
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->comment('用户ID');
            
            // JSON字段：核心数据结构
            $table->json('basic_info')->nullable()->comment('基础信息：age, gender, height, weight等');
            $table->json('fitness_goals')->nullable()->comment('健身目标：primary_goals, target_weight等');
            $table->json('training_preferences')->nullable()->comment('训练偏好：training_split, available_equipment等');
            $table->json('health_status')->nullable()->comment('健康状况：injuries, chronic_diseases等');
            $table->json('nutrition_profile')->nullable()->comment('营养档案：daily_calories, protein_intake等');
            $table->json('strength_data')->nullable()->comment('力量数据：bench_press, squat, deadlift等');
            $table->json('ffmi_assessment')->nullable()->comment('FFMI评估：ffmi, bmi, assessment等');
            
            // 元数据字段
            $table->integer('version')->default(1)->comment('数据版本号');
            $table->timestamps();
            
            // 同步相关字段（用于MCO服务）
            $table->timestamp('last_sync_at')->nullable()->comment('最后同步时间');
            $table->string('sync_status', 50)->default('synced')->comment('同步状态');
            $table->boolean('is_mcp_temp')->default(false)->comment('是否为MCP临时数据');
            $table->string('mcp_session_id', 100)->nullable()->comment('MCP会话ID');
            $table->string('sync_source', 50)->nullable()->comment('同步来源');
            
            // 外键和索引
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('sync_status');
            $table->index('is_mcp_temp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};

































