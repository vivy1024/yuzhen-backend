<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 添加增强字段到 exercises 表
 * 
 * 从 Neo4j 增强版数据集补充的字段：
 * - 训练参数：intensity_percentage
 * - 安全信息：safety_pre_check, equipment_risks
 * - 营养建议：key_nutrients, recommended_foods, nutrition_timing
 * 
 * @version 1.0.0
 * @date 2026-01-04
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            // 训练参数（部分字段已存在，只添加缺失的）
            if (!Schema::hasColumn('exercises', 'intensity_percentage')) {
                $table->string('intensity_percentage', 50)->nullable()->after('rest_period')->comment('训练强度百分比');
            }
            
            // 安全信息
            if (!Schema::hasColumn('exercises', 'safety_pre_check')) {
                $table->json('safety_pre_check')->nullable()->after('safety_level')->comment('训练前检查项');
            }
            if (!Schema::hasColumn('exercises', 'equipment_risks')) {
                $table->json('equipment_risks')->nullable()->after('safety_pre_check')->comment('器械风险');
            }
            
            // 营养建议
            if (!Schema::hasColumn('exercises', 'key_nutrients')) {
                $table->json('key_nutrients')->nullable()->after('rom_requirements')->comment('关键营养素');
            }
            if (!Schema::hasColumn('exercises', 'recommended_foods')) {
                $table->json('recommended_foods')->nullable()->after('key_nutrients')->comment('推荐食物');
            }
            if (!Schema::hasColumn('exercises', 'nutrition_timing')) {
                $table->string('nutrition_timing', 255)->nullable()->after('recommended_foods')->comment('营养补充时机');
            }
            
            // 字段命名规范化（添加 _zh/_en 后缀版本）
            if (!Schema::hasColumn('exercises', 'difficulty_zh')) {
                $table->string('difficulty_zh', 50)->nullable()->after('difficulty')->comment('难度中文');
            }
            if (!Schema::hasColumn('exercises', 'force_zh')) {
                $table->string('force_zh', 50)->nullable()->after('force_type')->comment('力量类型中文');
            }
            if (!Schema::hasColumn('exercises', 'mechanic_zh')) {
                $table->string('mechanic_zh', 50)->nullable()->after('mechanic_type')->comment('动作类型中文');
            }
            if (!Schema::hasColumn('exercises', 'grips_zh')) {
                $table->json('grips_zh')->nullable()->after('grips')->comment('握法中文');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $columns = [
                'intensity_percentage',
                'safety_pre_check',
                'equipment_risks',
                'key_nutrients',
                'recommended_foods',
                'nutrition_timing',
                'difficulty_zh',
                'force_zh',
                'mechanic_zh',
                'grips_zh',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('exercises', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
