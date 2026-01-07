<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为exercises表添加安全相关字段
 * 
 * 需求来源: Requirements 15.2, 15.3, 15.4
 * - 显示安全警告
 * - 显示禁忌条件
 * - 显示进阶建议
 * 
 * 数据来源: Neo4j Exercise节点的安全字段
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            // 安全信息字段
            $table->string('safety_level', 20)->nullable()->after('smart_tags')
                ->comment('安全等级: LOW_RISK/MEDIUM_RISK/HIGH_RISK');
            $table->text('safety_warning_signs')->nullable()->after('safety_level')
                ->comment('安全警告信息');
            $table->json('contraindications')->nullable()->after('safety_warning_signs')
                ->comment('禁忌条件列表');
            
            // 技术细节字段
            $table->string('kinetic_chain_type', 20)->nullable()->after('contraindications')
                ->comment('运动链类型: open_chain/closed_chain/mixed');
            $table->json('technique_checkpoints')->nullable()->after('kinetic_chain_type')
                ->comment('技术检查点列表');
            $table->json('rom_requirements')->nullable()->after('technique_checkpoints')
                ->comment('关节活动度要求');
            
            // 进阶相关字段
            $table->json('progression_options')->nullable()->after('rom_requirements')
                ->comment('进阶动作选项');
            $table->json('regression_options')->nullable()->after('progression_options')
                ->comment('退阶动作选项');
            
            // 训练参数字段
            $table->string('rep_range', 20)->nullable()->after('regression_options')
                ->comment('推荐次数范围');
            $table->string('set_range', 20)->nullable()->after('rep_range')
                ->comment('推荐组数范围');
            $table->string('rest_period', 30)->nullable()->after('set_range')
                ->comment('推荐休息时间');
            
            // 索引
            $table->index('safety_level');
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropIndex(['safety_level']);
            
            $table->dropColumn([
                'safety_level',
                'safety_warning_signs',
                'contraindications',
                'kinetic_chain_type',
                'technique_checkpoints',
                'rom_requirements',
                'progression_options',
                'regression_options',
                'rep_range',
                'set_range',
                'rest_period',
            ]);
        });
    }
};
