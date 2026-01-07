<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MembershipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * 会员体系设计原则（MVP阶段 - 简化版）：
     * 
     * 当前开放：
     * 1. 免费版：基础功能 + 有限AI对话（2次/天）
     * 2. 暖心会员：¥6/月（首充福利），3次/天复杂AI场景
     * 
     * 后续规划（Agent模式开发完成后）：
     * - 能量会员：Agent模式 + 更多高级功能
     * 
     * 成本分析（基于DeepSeek API）：
     * - 单次DAG模板执行：~¥0.05
     * - 暖心会员3次/天 × 30天 = 90次/月
     * - 假设实际使用率50%：45次 × ¥0.05 = ¥2.25成本
     * - ¥6定价：毛利¥3.75（62.5%毛利率）✅ 不亏损
     * 
     * 13个DAG模板：
     * greeting, quick_consultation, exercise_optimization, progress_analysis,
     * safety_assessment, complete_training_plan, nutrition_planning,
     * comprehensive_fitness, rehabilitation_training, posture_correction,
     * plan_adjustment, fat_loss_program, strength_program
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
        
        // 按复杂度分类
        $simpleTemplates = ['greeting', 'quick_consultation', 'exercise_optimization'];
        $mediumTemplates = ['progress_analysis', 'safety_assessment', 'nutrition_planning', 'posture_correction', 'plan_adjustment'];
        $complexTemplates = ['complete_training_plan', 'comprehensive_fitness', 'rehabilitation_training', 'fat_loss_program', 'strength_program'];
        
        $memberships = [
            [
                'name' => '免费版',
                'slug' => 'free',
                'tier' => 'free',
                'price' => 0.00,
                'duration_days' => 365, // 永久有效
                'max_training_plans' => 3,
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => false,
                'coach_service' => false,
                'description' => '体验AI健身顾问，全部13个AI场景免费开放！按复杂度分级使用次数。',
                'features' => json_encode([
                    '✅ 1790+动作库完全免费',
                    '✅ 1880+食物库完全免费',
                    '✅ TDEE/BMI/FFMI计算器',
                    '✅ 最多3个训练计划',
                    '✅ 全部13个AI场景',
                    '📊 简单场景 5次/天',
                    '📊 中等场景 2次/天',
                    '📊 复杂场景 1次/天'
                ], JSON_UNESCAPED_UNICODE),
                'limits' => json_encode([
                    'ai_queries_per_day' => 8, // 总次数上限
                    'ai_plans_per_week' => 0,
                    'training_plans' => 3,
                    'dag_templates' => $allTemplates,
                    'dag_template_count' => 13,
                    // 按复杂度分级限制
                    'complexity_limits' => [
                        'simple' => 5,   // complexity 1
                        'medium' => 2,   // complexity 2
                        'complex' => 1   // complexity 3
                    ],
                    'simple_templates' => $simpleTemplates,
                    'medium_templates' => $mediumTemplates,
                    'complex_templates' => $complexTemplates
                ]),
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => '暖心会员',
                'slug' => 'warmheart',
                'tier' => 'warmheart',
                'price' => 6.00, // 首充福利价
                'duration_days' => 30,
                'max_training_plans' => 10,
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => true,
                'coach_service' => false,
                'description' => '🎁 首充福利！更多AI对话次数，复杂场景翻倍！',
                'features' => json_encode([
                    '🎁 首充福利价 ¥6/月',
                    '✅ 全部13个AI场景',
                    '📊 简单场景 10次/天',
                    '📊 中等场景 5次/天',
                    '📊 复杂场景 2次/天',
                    '✅ 完整训练计划生成',
                    '✅ 营养规划方案',
                    '✅ 最多10个训练计划',
                    '📝 欢迎反馈帮助我们改进'
                ], JSON_UNESCAPED_UNICODE),
                'limits' => json_encode([
                    'ai_queries_per_day' => 17, // 总次数上限
                    'ai_plans_per_week' => 2,
                    'training_plans' => 10,
                    'dag_templates' => $allTemplates,
                    'dag_template_count' => 13,
                    // 按复杂度分级限制
                    'complexity_limits' => [
                        'simple' => 10,  // complexity 1
                        'medium' => 5,   // complexity 2
                        'complex' => 2   // complexity 3
                    ],
                    'simple_templates' => $simpleTemplates,
                    'medium_templates' => $mediumTemplates,
                    'complex_templates' => $complexTemplates
                ]),
                'sort_order' => 2,
                'is_active' => true,
            ],
            // 能量会员暂不开放，等Agent模式开发完成后再规划
            [
                'name' => '能量会员',
                'slug' => 'energy',
                'tier' => 'energy',
                'price' => 0.00, // 暂不定价
                'duration_days' => 30,
                'max_training_plans' => -1,
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => true,
                'coach_service' => true,
                'description' => '🚧 即将推出：Agent模式，更智能的AI健身教练。期待您的反馈帮助我们确定功能和定价！',
                'features' => json_encode([
                    '🚧 即将推出',
                    '🔜 Agent模式（LLM动态决策）',
                    '🔜 无限AI对话',
                    '🔜 深度个性化方案',
                    '🔜 多轮交互优化',
                    '🔜 专属客服支持',
                    '💬 期待您的反馈，帮助我们确定功能和定价'
                ], JSON_UNESCAPED_UNICODE),
                'limits' => json_encode([
                    'ai_queries_per_day' => -1,
                    'ai_plans_per_day' => -1,
                    'training_plans' => -1,
                    'dag_templates' => $allTemplates,
                    'dag_template_count' => 13
                ]),
                'sort_order' => 3,
                'is_active' => false, // 暂不开放
            ],
        ];

        foreach ($memberships as $membership) {
            DB::table('memberships')->updateOrInsert(
                ['slug' => $membership['slug']],
                array_merge($membership, [
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✅ 会员等级数据初始化完成（MVP阶段）');
        $this->command->info('   - 免费版: ¥0 (2次AI/天, 2个DAG模板)');
        $this->command->info('   - 暖心会员: ¥6/月 首充福利 (3次AI/天, 全部13个DAG模板)');
        $this->command->info('   - 能量会员: 🚧 即将推出 (Agent模式)');
    }
}

