<?php

namespace App\Services\Calculator;

/**
 * TDEE 计算器
 * Mifflin-St Jeor BMR + 活动系数 TDEE + 目标热量调整 + 三大营养素分配
 */
class TDEECalculator
{
    /** 活动系数映射 */
    private const ACTIVITY_MULTIPLIERS = [
        'sedentary'        => 1.2,   // 久坐
        'lightly_active'   => 1.375, // 轻度活动（1-3天/周）
        'moderately_active' => 1.55, // 中度活动（3-5天/周）
        'very_active'      => 1.725, // 高度活动（6-7天/周）
        'extremely_active' => 1.9,   // 极高活动（体力劳动+训练）
    ];

    /** 目标热量调整比例 */
    private const GOAL_ADJUSTMENTS = [
        'fat_loss'     => -0.20, // 减脂：-20%
        'mild_fat_loss' => -0.10, // 温和减脂：-10%
        'maintenance'  => 0.0,   // 维持
        'lean_bulk'    => 0.10,  // 精益增肌：+10%
        'hypertrophy'  => 0.15,  // 增肌：+15%
        'recomp'       => -0.05, // 重组：-5%
    ];

    /**
     * 计算 TDEE 及相关指标
     *
     * @param array $params [age, gender, weight_kg, height_cm, activity_level, fitness_goal]
     * @return array [bmr, tdee, target_calories, deficit_or_surplus, macros, formula_used]
     */
    public static function calculate(array $params): array
    {
        $bmr = self::calculateBMR(
            $params['gender'],
            $params['weight_kg'],
            $params['height_cm'],
            $params['age']
        );

        $multiplier = self::ACTIVITY_MULTIPLIERS[$params['activity_level']] ?? 1.55;
        $tdee = $bmr * $multiplier;

        $goal = $params['fitness_goal'] ?? 'maintenance';
        $adjustment = self::GOAL_ADJUSTMENTS[$goal] ?? 0.0;
        $targetCalories = $tdee * (1 + $adjustment);

        $macros = self::distributeMacros(
            $targetCalories,
            $params['weight_kg'],
            $goal
        );

        return [
            'bmr'                => (int) round($bmr),
            'tdee'               => (int) round($tdee),
            'target_calories'    => (int) round($targetCalories),
            'deficit_or_surplus' => (int) round($tdee * $adjustment),
            'activity_level'     => $params['activity_level'],
            'fitness_goal'       => $goal,
            'macros'             => $macros,
            'formula_used'       => 'mifflin_st_jeor',
        ];
    }

    /**
     * Mifflin-St Jeor BMR 公式
     * 男: 10×体重(kg) + 6.25×身高(cm) - 5×年龄 + 5
     * 女: 10×体重(kg) + 6.25×身高(cm) - 5×年龄 - 161
     */
    private static function calculateBMR(string $gender, float $weight, float $height, int $age): float
    {
        $base = 10 * $weight + 6.25 * $height - 5 * $age;

        return $gender === 'male' ? $base + 5 : $base - 161;
    }

    /**
     * 三大营养素分配
     * 蛋白质：按体重和目标分配
     * 脂肪：占总热量 25-30%
     * 碳水：补齐剩余热量
     */
    private static function distributeMacros(float $calories, float $weightKg, string $goal): array
    {
        // 蛋白质 g/kg 体重
        $proteinPerKg = match ($goal) {
            'fat_loss', 'mild_fat_loss' => 2.2,
            'hypertrophy', 'lean_bulk'  => 2.0,
            'recomp'                    => 2.2,
            default                     => 1.8,
        };
        $proteinG = $weightKg * $proteinPerKg;
        $proteinCal = $proteinG * 4;

        // 脂肪占总热量比例
        $fatRatio = match ($goal) {
            'fat_loss', 'mild_fat_loss' => 0.25,
            default                     => 0.28,
        };
        $fatCal = $calories * $fatRatio;
        $fatG = $fatCal / 9;

        // 碳水补齐
        $carbsCal = max(0, $calories - $proteinCal - $fatCal);
        $carbsG = $carbsCal / 4;

        return [
            'protein_g'   => (int) round($proteinG),
            'protein_cal' => (int) round($proteinCal),
            'fat_g'       => (int) round($fatG),
            'fat_cal'     => (int) round($fatCal),
            'carbs_g'     => (int) round($carbsG),
            'carbs_cal'   => (int) round($carbsCal),
            'protein_ratio' => round($proteinCal / max($calories, 1) * 100, 1),
            'fat_ratio'     => round($fatCal / max($calories, 1) * 100, 1),
            'carbs_ratio'   => round($carbsCal / max($calories, 1) * 100, 1),
        ];
    }
}
