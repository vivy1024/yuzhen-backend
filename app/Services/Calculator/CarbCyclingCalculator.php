<?php

namespace App\Services\Calculator;

/**
 * 碳循环计算器
 * 基于 TDEE 的高/中/低碳日分配，蛋白质恒定，脂肪补齐
 */
class CarbCyclingCalculator
{
    /** 碳水系数 (g/kg 体重) */
    private const CARB_MULTIPLIERS = [
        'high'   => ['min' => 3.0, 'max' => 4.0],
        'medium' => ['min' => 2.0, 'max' => 3.0],
        'low'    => ['min' => 1.0, 'max' => 1.5],
    ];

    /** 蛋白质恒定系数 (g/kg 体重) */
    private const PROTEIN_PER_KG = 2.0;

    /**
     * 计算碳循环周计划
     *
     * @param array $params [tdee, weight_kg, fitness_goal, training_days (0-6, 0=周一)]
     * @return array
     */
    public static function calculate(array $params): array
    {
        $tdee = (float) $params['tdee'];
        $weightKg = (float) $params['weight_kg'];
        $goal = $params['fitness_goal'] ?? 'maintenance';
        $trainingDays = $params['training_days'] ?? [0, 1, 2, 3, 4]; // 默认周一到周五

        // 蛋白质恒定
        $proteinG = $weightKg * self::PROTEIN_PER_KG;
        $proteinCal = $proteinG * 4;

        // 分类每天的碳水类型
        $dayNames = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
        $weeklyPlan = [];
        $totalCalories = 0;
        $highDays = 0;
        $mediumDays = 0;
        $lowDays = 0;

        for ($day = 0; $day < 7; $day++) {
            $isTrainingDay = in_array($day, $trainingDays, true);
            $carbType = self::determineCarbType($day, $trainingDays, $goal);

            $carbMultiplier = self::CARB_MULTIPLIERS[$carbType];
            $carbG = $weightKg * ($carbMultiplier['min'] + $carbMultiplier['max']) / 2;
            $carbCal = $carbG * 4;

            // 脂肪补齐剩余热量（基于当天目标热量）
            $dayCalTarget = self::getDayCalorieTarget($tdee, $carbType, $goal);
            $fatCal = max(0, $dayCalTarget - $proteinCal - $carbCal);
            $fatG = $fatCal / 9;

            $dayCal = $proteinCal + $carbCal + $fatCal;
            $totalCalories += $dayCal;

            $weeklyPlan[] = [
                'day'         => $day,
                'day_name'    => $dayNames[$day],
                'is_training' => $isTrainingDay,
                'carb_type'   => $carbType,
                'calories'    => (int) round($dayCal),
                'protein_g'   => (int) round($proteinG),
                'carbs_g'     => (int) round($carbG),
                'fat_g'       => (int) round($fatG),
            ];

            match ($carbType) {
                'high'   => $highDays++,
                'medium' => $mediumDays++,
                'low'    => $lowDays++,
            };
        }

        return [
            'weekly_plan'             => $weeklyPlan,
            'weekly_average_calories' => (int) round($totalCalories / 7),
            'weekly_total_calories'   => (int) round($totalCalories),
            'high_carb_days'          => $highDays,
            'medium_carb_days'        => $mediumDays,
            'low_carb_days'           => $lowDays,
            'constant_protein_g'      => (int) round($proteinG),
            'tdee_reference'          => (int) round($tdee),
        ];
    }

    /**
     * 判断某天的碳水类型
     */
    private static function determineCarbType(int $day, array $trainingDays, string $goal): string
    {
        $isTraining = in_array($day, $trainingDays, true);
        // 下一天是否训练
        $nextDay = ($day + 1) % 7;
        $isPreTraining = in_array($nextDay, $trainingDays, true);

        if ($isTraining) {
            return 'high';
        }

        // 减脂目标：休息日全部低碳
        if (in_array($goal, ['fat_loss', 'mild_fat_loss'])) {
            return 'low';
        }

        // 训练前一天：中碳（碳水储备）
        if ($isPreTraining) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * 根据碳水类型和目标获取当天热量目标
     */
    private static function getDayCalorieTarget(float $tdee, string $carbType, string $goal): float
    {
        $goalAdjustment = match ($goal) {
            'fat_loss'      => -0.15,
            'mild_fat_loss' => -0.08,
            'hypertrophy'   => 0.10,
            'lean_bulk'     => 0.08,
            default         => 0.0,
        };

        $dayAdjustment = match ($carbType) {
            'high'   => 0.10,
            'medium' => 0.0,
            'low'    => -0.10,
        };

        return $tdee * (1 + $goalAdjustment + $dayAdjustment);
    }
}
