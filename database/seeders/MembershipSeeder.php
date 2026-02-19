<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 会员套餐Seeder - 会员自动化控制系统
 * 
 * 插入6种套餐 + 2种首充优惠套餐 + 1种免费套餐
 * 
 * @version v2.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 9.1
 */
class MembershipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * 会员体系设计：
     * 
     * 免费版：
     * - 基础功能 + 有限AI对话
     * - DAG: 10次/天, Agent: 5次/天
     * - 2个基础DAG模板
     *
     * 暖心会员（WARMHEART）：
     * - 月卡¥6, 季卡¥15, 年卡¥50
     * - 首充体验¥1/7天
     * - DAG: 30次/天, Agent: 10次/天
     * - 全部13个DAG模板
     *
     * 能量会员（ENERGY）：
     * - 月卡¥29, 季卡¥69, 年卡¥199
     * - 首充体验¥9/7天
     * - DAG: 无限, Agent: 无限
     * - 全部13个DAG模板 + 无限Agent
     * 
     * 成本分析（基于DeepSeek API）：
     * - 单次DAG模板执行：~¥0.05
     * - 暖心会员30次/天 × 30天 = 900次/月
     * - 假设实际使用率30%：270次 × ¥0.05 = ¥13.5成本
     * - ¥6定价：需要控制使用率，或通过年卡摊薄成本
     */
    public function run(): void
    {
        // 全部13个DAG模板
        $allTemplates = [
            'greeting', 'quick_consultation', 'exercise_optimization', 
            'progress_analysis', 'safety_assessment', 'complete_training_plan', 
            'nutrition_planning', 'comprehensive_fitness', 'rehabilitation_training', 
            'posture_correction', 'plan_adjustment', 'fat_loss_program', 'strength_program'
        ];
        
        // 免费版只能使用2个基础模板
        $freeTemplates = ['greeting', 'simple_exercise_query'];
        
        $memberships = [
            // ==================== 免费版 ====================
            [
                'name' => '免费版',
                'slug' => 'free',
                'tier' => 'free',
                'price' => 0.00,
                'original_price' => 0.00,
                'duration_days' => 36500, // 100年，相当于永久
                'max_training_plans' => 3,
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => false,
                'coach_service' => false,
                'description' => '体验AI健身顾问基础功能，每日有限次数AI对话。',
                'features' => json_encode([
                    '✅ 1790+动作库完全免费',
                    '✅ 1880+食物库完全免费',
                    '✅ TDEE/BMI/FFMI计算器',
                    '✅ 最多3个训练计划',
                    '📊 DAG查询 10次/天',
                    '📊 Agent查询 5次/天',
                    '📊 2个基础AI场景',
                ], JSON_UNESCAPED_UNICODE),
                'limits' => json_encode([
                    'daily_dag_limit' => 10,
                    'daily_agent_limit' => 5,
                    'training_plans' => 3,
                    'dag_templates' => $freeTemplates,
                    'dag_template_count' => 2,
                    'can_use_agent' => true,
                    'advanced_analysis' => false
                ], JSON_UNESCAPED_UNICODE),
                'is_first_purchase' => false,
                'sort_order' => 1,
                'is_active' => true,
            ],
            
            // ==================== 暖心会员套餐 ====================
            [
                'name' => '暖心7天体验',
                'slug' => 'warmheart-trial',
                'tier' => 'warmheart',
                'price' => 1.00,
                'original_price' => 6.00,
                'duration_days' => 7,
                'max_training_plans' => 10,
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => true,
                'coach_service' => false,
                'description' => '🎁 首充专享！仅¥1体验7天暖心会员全部功能！',
                'features' => json_encode([
                    '🎁 首充专享价 ¥1',
                    '✅ 全部13个AI场景',
                    '📊 DAG查询 30次/天',
                    '📊 Agent查询 10次/天',
                    '✅ 完整训练计划生成',
                    '✅ 营养规划方案',
                    '✅ 最多10个训练计划',
                    '⏰ 有效期7天'
                ], JSON_UNESCAPED_UNICODE),
                'limits' => json_encode([
                    'daily_dag_limit' => 30,
                    'daily_agent_limit' => 10,
                    'training_plans' => 10,
                    'dag_templates' => $allTemplates,
                    'dag_template_count' => 13,
                    'can_use_agent' => true,
                    'advanced_analysis' => true
                ], JSON_UNESCAPED_UNICODE),
                'is_first_purchase' => true,
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => '暖心月卡',
                'slug' => 'warmheart-monthly',
                'tier' => 'warmheart',
                'price' => 6.00,
                'original_price' => 9.00,
                'duration_days' => 30,
                'max_training_plans' => 10,
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => true,
                'coach_service' => false,
                'description' => '暖心会员月卡，全部AI场景解锁，每日30次DAG + 10次Agent查询。',
                'features' => json_encode([
                    '💰 ¥6/月',
                    '✅ 全部13个AI场景',
                    '📊 DAG查询 30次/天',
                    '📊 Agent查询 10次/天',
                    '✅ 完整训练计划生成',
                    '✅ 营养规划方案',
                    '✅ 最多10个训练计划',
                    '⏰ 有效期30天'
                ], JSON_UNESCAPED_UNICODE),
                'limits' => json_encode([
                    'daily_dag_limit' => 30,
                    'daily_agent_limit' => 10,
                    'training_plans' => 10,
                    'dag_templates' => $allTemplates,
                    'dag_template_count' => 13,
                    'can_use_agent' => true,
                    'advanced_analysis' => true
                ], JSON_UNESCAPED_UNICODE),
                'is_first_purchase' => false,
                'sort_order' => 11,
                'is_active' => true,
            ],
            [
                'name' => '暖心季卡',
                'slug' => 'warmheart-quarterly',
                'tier' => 'warmheart',
                'price' => 15.00,
                'original_price' => 27.00,
                'duration_days' => 90,
                'max_training_plans' => 10,
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => true,
                'coach_service' => false,
                'description' => '暖心会员季卡，相当于¥5/月，更划算！',
                'features' => json_encode([
                    '💰 ¥15/季（¥5/月）',
                    '🔥 省¥12',
                    '✅ 全部13个AI场景',
                    '📊 DAG查询 30次/天',
                    '📊 Agent查询 10次/天',
                    '✅ 完整训练计划生成',
                    '✅ 营养规划方案',
                    '✅ 最多10个训练计划',
                    '⏰ 有效期90天'
                ], JSON_UNESCAPED_UNICODE),
                'limits' => json_encode([
                    'daily_dag_limit' => 30,
                    'daily_agent_limit' => 10,
                    'training_plans' => 10,
                    'dag_templates' => $allTemplates,
                    'dag_template_count' => 13,
                    'can_use_agent' => true,
                    'advanced_analysis' => true
                ], JSON_UNESCAPED_UNICODE),
                'is_first_purchase' => false,
                'sort_order' => 12,
                'is_active' => true,
            ],
            [
                'name' => '暖心年卡',
                'slug' => 'warmheart-yearly',
                'tier' => 'warmheart',
                'price' => 50.00,
                'original_price' => 108.00,
                'duration_days' => 365,
                'max_training_plans' => 10,
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => true,
                'coach_service' => false,
                'description' => '暖心会员年卡，相当于¥4.2/月，最划算！',
                'features' => json_encode([
                    '💰 ¥50/年（¥4.2/月）',
                    '🔥 省¥58',
                    '✅ 全部13个AI场景',
                    '📊 DAG查询 30次/天',
                    '📊 Agent查询 10次/天',
                    '✅ 完整训练计划生成',
                    '✅ 营养规划方案',
                    '✅ 最多10个训练计划',
                    '⏰ 有效期365天'
                ], JSON_UNESCAPED_UNICODE),
                'limits' => json_encode([
                    'daily_dag_limit' => 30,
                    'daily_agent_limit' => 10,
                    'training_plans' => 10,
                    'dag_templates' => $allTemplates,
                    'dag_template_count' => 13,
                    'can_use_agent' => true,
                    'advanced_analysis' => true
                ], JSON_UNESCAPED_UNICODE),
                'is_first_purchase' => false,
                'sort_order' => 13,
                'is_active' => true,
            ],
            
            // ==================== 能量会员套餐 ====================
            [
                'name' => '能量7天体验',
                'slug' => 'energy-trial',
                'tier' => 'energy',
                'price' => 9.00,
                'original_price' => 29.00,
                'duration_days' => 7,
                'max_training_plans' => -1, // 无限
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => true,
                'coach_service' => true,
                'description' => '🎁 首充专享！仅¥9体验7天能量会员全部功能，含Agent模式！',
                'features' => json_encode([
                    '🎁 首充专享价 ¥9',
                    '✅ 全部13个AI场景',
                    '🚀 Agent模式（智能对话）',
                    '📊 DAG查询 无限',
                    '📊 Agent查询 无限',
                    '✅ 无限训练计划',
                    '✅ 深度个性化方案',
                    '⏰ 有效期7天'
                ], JSON_UNESCAPED_UNICODE),
                'limits' => json_encode([
                    'daily_dag_limit' => -1, // 无限
                    'daily_agent_limit' => -1, // 无限
                    'training_plans' => -1, // 无限
                    'dag_templates' => $allTemplates,
                    'dag_template_count' => 13,
                    'can_use_agent' => true,
                    'advanced_analysis' => true
                ], JSON_UNESCAPED_UNICODE),
                'is_first_purchase' => true,
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'name' => '能量月卡',
                'slug' => 'energy-monthly',
                'tier' => 'energy',
                'price' => 29.00,
                'original_price' => 39.00,
                'duration_days' => 30,
                'max_training_plans' => -1,
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => true,
                'coach_service' => true,
                'description' => '能量会员月卡，解锁Agent模式，无限AI对话！',
                'features' => json_encode([
                    '💰 ¥29/月',
                    '✅ 全部13个AI场景',
                    '🚀 Agent模式（智能对话）',
                    '📊 DAG查询 无限',
                    '📊 Agent查询 无限',
                    '✅ 无限训练计划',
                    '✅ 深度个性化方案',
                    '⏰ 有效期30天'
                ], JSON_UNESCAPED_UNICODE),
                'limits' => json_encode([
                    'daily_dag_limit' => -1,
                    'daily_agent_limit' => -1,
                    'training_plans' => -1,
                    'dag_templates' => $allTemplates,
                    'dag_template_count' => 13,
                    'can_use_agent' => true,
                    'advanced_analysis' => true
                ], JSON_UNESCAPED_UNICODE),
                'is_first_purchase' => false,
                'sort_order' => 21,
                'is_active' => true,
            ],
            [
                'name' => '能量季卡',
                'slug' => 'energy-quarterly',
                'tier' => 'energy',
                'price' => 69.00,
                'original_price' => 117.00,
                'duration_days' => 90,
                'max_training_plans' => -1,
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => true,
                'coach_service' => true,
                'description' => '能量会员季卡，相当于¥23/月，更划算！',
                'features' => json_encode([
                    '💰 ¥69/季（¥23/月）',
                    '🔥 省¥48',
                    '✅ 全部13个AI场景',
                    '🚀 Agent模式（智能对话）',
                    '📊 DAG查询 无限',
                    '📊 Agent查询 无限',
                    '✅ 无限训练计划',
                    '✅ 深度个性化方案',
                    '⏰ 有效期90天'
                ], JSON_UNESCAPED_UNICODE),
                'limits' => json_encode([
                    'daily_dag_limit' => -1,
                    'daily_agent_limit' => -1,
                    'training_plans' => -1,
                    'dag_templates' => $allTemplates,
                    'dag_template_count' => 13,
                    'can_use_agent' => true,
                    'advanced_analysis' => true
                ], JSON_UNESCAPED_UNICODE),
                'is_first_purchase' => false,
                'sort_order' => 22,
                'is_active' => true,
            ],
            [
                'name' => '能量年卡',
                'slug' => 'energy-yearly',
                'tier' => 'energy',
                'price' => 199.00,
                'original_price' => 468.00,
                'duration_days' => 365,
                'max_training_plans' => -1,
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => true,
                'coach_service' => true,
                'description' => '能量会员年卡，相当于¥16.6/月，最划算！',
                'features' => json_encode([
                    '💰 ¥199/年（¥16.6/月）',
                    '🔥 省¥269',
                    '✅ 全部13个AI场景',
                    '🚀 Agent模式（智能对话）',
                    '📊 DAG查询 无限',
                    '📊 Agent查询 无限',
                    '✅ 无限训练计划',
                    '✅ 深度个性化方案',
                    '⏰ 有效期365天'
                ], JSON_UNESCAPED_UNICODE),
                'limits' => json_encode([
                    'daily_dag_limit' => -1,
                    'daily_agent_limit' => -1,
                    'training_plans' => -1,
                    'dag_templates' => $allTemplates,
                    'dag_template_count' => 13,
                    'can_use_agent' => true,
                    'advanced_analysis' => true
                ], JSON_UNESCAPED_UNICODE),
                'is_first_purchase' => false,
                'sort_order' => 23,
                'is_active' => true,
            ],
        ];

        foreach ($memberships as $membership) {
            DB::table('memberships')->updateOrInsert(
                ['slug' => $membership['slug']],
                array_merge($membership, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✅ 会员套餐数据初始化完成');
        $this->command->info('');
        $this->command->info('📦 套餐列表（共9种）：');
        $this->command->info('   免费版: ¥0 (DAG 10次/天, Agent 5次/天, 2个模板)');
        $this->command->info('');
        $this->command->info('   暖心会员（WARMHEART）：');
        $this->command->info('   - 暖心7天体验: ¥1 (首充专享)');
        $this->command->info('   - 暖心月卡: ¥6/30天 (DAG 30次 + Agent 10次/天)');
        $this->command->info('   - 暖心季卡: ¥15/90天');
        $this->command->info('   - 暖心年卡: ¥50/365天');
        $this->command->info('');
        $this->command->info('   能量会员（ENERGY）：');
        $this->command->info('   - 能量7天体验: ¥9 (首充专享)');
        $this->command->info('   - 能量月卡: ¥29/30天 (DAG+Agent 无限)');
        $this->command->info('   - 能量季卡: ¥69/90天');
        $this->command->info('   - 能量年卡: ¥199/365天');
    }
}
