<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 重建 exercises 表 - 字段命名与源数据完全一致
 * 
 * 源数据字段规范：
 * - 中文字段：xxx_zh
 * - 英文字段：xxx_en
 * - 通用字段：无后缀
 * 
 * @version 2.0.0
 * @date 2026-01-04
 */
return new class extends Migration
{
    public function up(): void
    {
        // 禁用外键检查
        \DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // 删除旧表
        Schema::dropIfExists('exercises');
        
        // 创建新表
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            
            // === 基础信息（中英文对照）===
            $table->string('name_en', 255)->comment('名称英文');
            $table->string('name_zh', 255)->nullable()->comment('名称中文');
            $table->string('slug', 255)->nullable()->unique()->comment('URL友好名称');
            $table->text('description_en')->nullable()->comment('描述英文');
            $table->text('description_zh')->nullable()->comment('描述中文');
            
            // === 肌肉信息 ===
            $table->string('primary_muscle_en', 100)->nullable()->comment('主要肌肉英文');
            $table->string('primary_muscle_zh', 100)->nullable()->comment('主要肌肉中文');
            $table->json('all_muscles_zh')->nullable()->comment('所有肌肉中文');
            
            // === 器械信息 ===
            $table->string('equipment_en', 100)->nullable()->comment('器械英文');
            $table->string('equipment_zh', 100)->nullable()->comment('器械中文');
            
            // === 难度信息 ===
            $table->string('difficulty_en', 50)->nullable()->comment('难度英文');
            $table->string('difficulty_zh', 50)->nullable()->comment('难度中文');
            
            // === 力量类型 ===
            $table->string('force_en', 50)->nullable()->comment('力量类型英文');
            $table->string('force_zh', 50)->nullable()->comment('力量类型中文');
            
            // === 动作类型 ===
            $table->string('mechanic_en', 50)->nullable()->comment('动作类型英文');
            $table->string('mechanic_zh', 50)->nullable()->comment('动作类型中文');
            
            // === 握法 ===
            $table->json('grips_en')->nullable()->comment('握法英文');
            $table->json('grips_zh')->nullable()->comment('握法中文');
            
            // === 正确步骤 ===
            $table->json('correct_steps_en')->nullable()->comment('正确步骤英文');
            $table->json('correct_steps_zh')->nullable()->comment('正确步骤中文');
            
            // === 智能标签 ===
            $table->json('smart_tags')->nullable()->comment('智能标签');
            
            // === 训练参数 ===
            $table->string('rep_range', 50)->nullable()->comment('推荐次数范围');
            $table->string('set_range', 50)->nullable()->comment('推荐组数范围');
            $table->string('rest_period', 50)->nullable()->comment('休息时间');
            $table->string('intensity_percentage', 50)->nullable()->comment('训练强度百分比');
            
            // === 安全信息 ===
            $table->string('safety_level', 50)->nullable()->comment('安全等级');
            $table->json('safety_pre_check')->nullable()->comment('训练前检查项');
            $table->json('equipment_risks')->nullable()->comment('器械风险');
            
            // === 技术细节 ===
            $table->string('kinetic_chain_type', 50)->nullable()->comment('动力链类型');
            $table->json('technique_checkpoints')->nullable()->comment('技术检查点');
            $table->json('rom_requirements')->nullable()->comment('活动范围要求');
            
            // === 营养建议 ===
            $table->json('key_nutrients')->nullable()->comment('关键营养素');
            $table->json('recommended_foods')->nullable()->comment('推荐食物');
            $table->string('nutrition_timing', 255)->nullable()->comment('营养补充时机');
            
            // === 进阶选项 ===
            $table->json('progression_options')->nullable()->comment('进阶选项');
            $table->json('regression_options')->nullable()->comment('退阶选项');
            
            // === 分类 ===
            $table->json('categories')->nullable()->comment('分类');
            
            // === 数据来源 ===
            $table->string('data_source', 50)->nullable()->comment('数据来源');
            $table->string('source_reference', 255)->nullable()->comment('来源引用');
            $table->string('license_type', 50)->nullable()->comment('授权类型');
            $table->string('original_source', 255)->nullable()->comment('原始来源');
            $table->timestamp('last_verified_at')->nullable()->comment('最后验证时间');
            $table->string('verified_by', 100)->nullable()->comment('验证人');
            
            // === 统计信息 ===
            $table->integer('rating')->default(0)->comment('评分');
            $table->integer('view_count')->default(0)->comment('浏览次数');
            
            // === 时间戳 ===
            $table->timestamps();
            
            // === 索引 ===
            $table->index('primary_muscle_en');
            $table->index('primary_muscle_zh');
            $table->index('equipment_en');
            $table->index('equipment_zh');
            $table->index('difficulty_en');
            $table->index('safety_level');
        });
        
        // 重新启用外键检查
        \DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
