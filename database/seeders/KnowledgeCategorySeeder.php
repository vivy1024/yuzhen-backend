<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KnowledgeCategory;

class KnowledgeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => '运动科学',
                'slug' => 'exercise-science',
                'icon' => '🏋️',
                'sort_order' => 1,
                'description' => '运动生理学、训练原理、周期化编程',
                'children' => [
                    ['name' => '运动生理学', 'slug' => 'exercise-physiology', 'icon' => '🫀', 'sort_order' => 1, 'description' => '能量系统、肌肉适应、心肺功能'],
                    ['name' => '力量训练', 'slug' => 'strength-training', 'icon' => '💪', 'sort_order' => 2, 'description' => '力量发展原理、训练方法、周期化'],
                    ['name' => '训练编程', 'slug' => 'program-design', 'icon' => '📋', 'sort_order' => 3, 'description' => '训练计划设计、负荷管理、进阶策略'],
                    ['name' => '运动生物力学', 'slug' => 'biomechanics', 'icon' => '⚙️', 'sort_order' => 4, 'description' => '动作分析、力学原理、技术优化'],
                    ['name' => '有氧训练', 'slug' => 'cardio-training', 'icon' => '🏃', 'sort_order' => 5, 'description' => '心肺耐力、HIIT、有氧适应'],
                ],
            ],
            [
                'name' => '运动营养',
                'slug' => 'sports-nutrition',
                'icon' => '🥗',
                'sort_order' => 2,
                'description' => '运动营养学、膳食计划、补剂科学',
                'children' => [
                    ['name' => '宏量营养素', 'slug' => 'macronutrients', 'icon' => '🍖', 'sort_order' => 1, 'description' => '蛋白质、碳水化合物、脂肪的运动需求'],
                    ['name' => '营养时机', 'slug' => 'nutrient-timing', 'icon' => '⏰', 'sort_order' => 2, 'description' => '训练前中后营养策略'],
                    ['name' => '体重管理', 'slug' => 'weight-management', 'icon' => '⚖️', 'sort_order' => 3, 'description' => '减脂、增肌、体重维持的营养策略'],
                    ['name' => '补剂科学', 'slug' => 'supplements', 'icon' => '💊', 'sort_order' => 4, 'description' => '循证补剂评估、安全性、有效性'],
                    ['name' => '水分与电解质', 'slug' => 'hydration', 'icon' => '💧', 'sort_order' => 5, 'description' => '运动补水策略、电解质平衡'],
                ],
            ],
            [
                'name' => '康复与预防',
                'slug' => 'rehab-prevention',
                'icon' => '🩺',
                'sort_order' => 3,
                'description' => '运动康复、伤病预防、体态矫正',
                'children' => [
                    ['name' => '伤病预防', 'slug' => 'injury-prevention', 'icon' => '🛡️', 'sort_order' => 1, 'description' => '常见运动损伤预防、热身冷却'],
                    ['name' => '运动康复', 'slug' => 'rehabilitation', 'icon' => '🔄', 'sort_order' => 2, 'description' => '损伤后康复训练、渐进回归'],
                    ['name' => '体态矫正', 'slug' => 'posture-correction', 'icon' => '🧍', 'sort_order' => 3, 'description' => '常见体态问题评估与矫正'],
                    ['name' => '柔韧性与活动度', 'slug' => 'flexibility-mobility', 'icon' => '🧘', 'sort_order' => 4, 'description' => '拉伸、关节活动度、筋膜放松'],
                    ['name' => '恢复策略', 'slug' => 'recovery', 'icon' => '😴', 'sort_order' => 5, 'description' => '睡眠、恢复手段、过度训练预防'],
                ],
            ],
        ];

        foreach ($categories as $topLevel) {
            $children = $topLevel['children'] ?? [];
            unset($topLevel['children']);

            $parent = KnowledgeCategory::create($topLevel);

            foreach ($children as $child) {
                $child['parent_id'] = $parent->id;
                KnowledgeCategory::create($child);
            }
        }
    }
}
