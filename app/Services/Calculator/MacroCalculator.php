<?php

namespace App\Services\Calculator;

/**
 * 宏量营养素计算器
 * 按目标（增肌/减脂/维持/重组）分配蛋白质/碳水/脂肪克数
 */
class MacroCalculator
{
    /** 目标对应的宏量比例 [蛋白质%, 碳水%, 脂肪%] */
    private const GOAL_RATIOS = [
        'fat_loss'     => ['protein' => 40, 'carbs' => 30, 'fat' => 30],
        'mild_fat_loss' => ['protein' => 35, 'carbs' => 35, 'fat' => 30],
        'maintenance'  => ['protein' => 30, 'carbs' => 40, 'fat' => 30],
        'lean_bulk'    => ['protein' => 30, 'carbs' => 45, 'fat' => 25],
        'hypertrophy'  => ['protein' => 30, 'carbs' => 45, 'fat' => 25],
        'recomp'       => ['protein' => 35, 'carbs' => 35, 'fat' => 30],
    ];

    /** 蛋白质 g/kg 体重参考 */
    private const PROTEIN_PER_KG = [
        'fat_loss'     => 2.2,
        'mild_fat_loss' => 2.0,
        'maintenance'  => 1.8,
        'lean_bulk'    => 2.0,
        'hypertrophy'  => 2.0,
        'recomp'       => 2.2,
    ];

    /**
     * 计算宏量营养素分配
     *
     * @param array $params [target_calories, weight_kg, fitness_goal, method?]
     * @return array
     */
    public static function calculate(array $params): array
    {
        $calories = (float) $params['target_calories'];
        $weightKg = (float) $params['weight_kg'];
        $goal = $params['fitness_goal'] ?? 'maintenance';
        $method = $params['method'] ?? 'balanced';

        return match ($method) {
            'body_weight' => self::byBodyWeight($calories, $weightKg, $goal),
            'ratio'       => self::byRatio($calories, $weightKg, $goal),
            default       => self::balanced($calories, $weightKg, $goal),
        };
    }

    /**
     * 平衡法：蛋白质按体重，脂肪按比例，碳水补齐
     */
    private static function balanced(float $calories, float $weightKg, string $goal): array
    {
        $proteinPerKg = self::PROTEIN_PER_KG[$goal] ?? 1.8;
        $proteinG = $weightKg * $proteinPerKg;
        $proteinCal = $proteinG * 4;

        // 脂肪占 25-30%
        $fatRatio = in_array($goal, ['fat_loss', 'mild_fat_loss', 'recomp']) ? 0.25 : 0.28;
        $fatCal = $calories * $fatRatio;
        $fatG = $fatCal / 9;

        // 碳水补齐
        $carbsCal = max(0, $calories - $proteinCal - $fatCal);
        $carbsG = $carbsCal / 4;

        return self::buildResult($proteinG, $carbsG, $fatG, $calories, $goal, 'balanced', $weightKg);
    }

    /**
     * 体重法：全部按 g/kg 体重计算
     */
    private static function byBodyWeight(float $calories, float $weightKg, string $goal): array
    {
        $proteinPerKg = self::PROTEIN_PER_KG[$goal] ?? 1.8;
        $proteinG = $weightKg * $proteinPerKg;

        $carbsPerKg = match ($goal) {
            'fat_loss'      => 2.0,
            'mild_fat_loss' => 2.5,
            'hypertrophy', 'lean_bulk' => 4.0,
            'recomp'        => 2.5,
            default         => 3.0,
        };
        $carbsG = $weightKg * $carbsPerKg;

        // 脂肪补齐
        $proteinCal = $proteinG * 4;
        $carbsCal = $carbsG * 4;
        $fatCal = max(0, $calories - $proteinCal - $carbsCal);
        $fatG = $fatCal / 9;

        // 脂肪最低保障：0.8g/kg
        if ($fatG < $weightKg * 0.8) {
            $fatG = $weightKg * 0.8;
            $fatCal = $fatG * 9;
            $carbsCal = max(0, $calories - $proteinCal - $fatCal);
            $carbsG = $carbsCal / 4;
        }

        return self::buildResult($proteinG, $carbsG, $fatG, $calories, $goal, 'body_weight', $weightKg);
    }

    /**
     * 比例法：按固定百分比分配
     */
    private static function byRatio(float $calories, float $weightKg, string $goal): array
    {
        $ratios = self::GOAL_RATIOS[$goal] ?? self::GOAL_RATIOS['maintenance'];

        $proteinCal = $calories * $ratios['protein'] / 100;
        $carbsCal = $calories * $ratios['carbs'] / 100;
        $fatCal = $calories * $ratios['fat'] / 100;

        return self::buildResult($proteinCal / 4, $carbsCal / 4, $fatCal / 9, $calories, $goal, 'ratio', $weightKg);
    }

    /**
     * 构建返回结果
     */
    private static function buildResult(float $proteinG, float $carbsG, float $fatG, float $calories, string $goal, string $method, float $weightKg = 70): array
    {
        $proteinCal = $proteinG * 4;
        $carbsCal = $carbsG * 4;
        $fatCal = $fatG * 9;
        $totalCal = $proteinCal + $carbsCal + $fatCal;

        return [
            'macros' => [
                'protein_g'   => (int) round($proteinG),
                'protein_cal' => (int) round($proteinCal),
                'carbs_g'     => (int) round($carbsG),
                'carbs_cal'   => (int) round($carbsCal),
                'fat_g'       => (int) round($fatG),
                'fat_cal'     => (int) round($fatCal),
            ],
            'ratios' => [
                'protein' => round($proteinCal / max($totalCal, 1) * 100, 1),
                'carbs'   => round($carbsCal / max($totalCal, 1) * 100, 1),
                'fat'     => round($fatCal / max($totalCal, 1) * 100, 1),
            ],
            'per_kg' => [
                'protein' => round($proteinG / max($weightKg, 1), 1),
                'carbs'   => round($carbsG / max($weightKg, 1), 1),
                'fat'     => round($fatG / max($weightKg, 1), 1),
            ],
            'total_calories'  => (int) round($totalCal),
            'target_calories' => (int) round($calories),
            'fitness_goal'    => $goal,
            'method'          => $method,
        ];
    }
}
