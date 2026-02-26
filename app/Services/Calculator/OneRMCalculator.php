<?php

namespace App\Services\Calculator;

/**
 * 1RM 计算器
 * Epley + Brzycki 公式，支持重量×次数 → 1RM 估算
 */
class OneRMCalculator
{
    /**
     * 计算 1RM 估算值
     *
     * @param array $params [weight_kg, reps, formula?]
     * @return array
     */
    public static function calculate(array $params): array
    {
        $weight = (float) $params['weight_kg'];
        $reps = (int) $params['reps'];
        $preferredFormula = $params['formula'] ?? 'average';

        // 1RM = 实际重量（1次就是1RM）
        if ($reps <= 0) {
            return self::buildResult($weight, $weight, $weight, 1, 'direct');
        }
        if ($reps === 1) {
            return self::buildResult($weight, $weight, $weight, 1, 'direct');
        }

        $epley = self::epley($weight, $reps);
        $brzycki = self::brzycki($weight, $reps);

        // 高次数（>12）时 Epley 更准，低次数时 Brzycki 更准
        $average = ($epley + $brzycki) / 2;

        $estimated1RM = match ($preferredFormula) {
            'epley'   => $epley,
            'brzycki' => $brzycki,
            default   => $average,
        };

        return self::buildResult($estimated1RM, $epley, $brzycki, $reps, $preferredFormula);
    }

    /**
     * 批量计算多个动作的 1RM
     *
     * @param array $exercises [['name' => string, 'weight_kg' => float, 'reps' => int], ...]
     * @return array
     */
    public static function calculateBatch(array $exercises): array
    {
        $results = [];
        foreach ($exercises as $exercise) {
            $results[] = array_merge(
                ['exercise' => $exercise['name'] ?? 'unknown'],
                self::calculate($exercise)
            );
        }
        return ['exercises' => $results];
    }

    /**
     * Epley 公式: 1RM = w × (1 + r/30)
     */
    private static function epley(float $weight, int $reps): float
    {
        return $weight * (1 + $reps / 30);
    }

    /**
     * Brzycki 公式: 1RM = w × 36 / (37 - r)
     * 注意：reps >= 37 时公式失效，回退到 Epley
     */
    private static function brzycki(float $weight, int $reps): float
    {
        if ($reps >= 37) {
            return self::epley($weight, $reps);
        }
        return $weight * 36 / (37 - $reps);
    }

    /**
     * 构建返回结果，包含百分比参考表
     */
    private static function buildResult(float $estimated1RM, float $epley, float $brzycki, int $reps, string $formula): array
    {
        $rm = round($estimated1RM, 1);

        return [
            'estimated_1rm'  => $rm,
            'epley_1rm'      => round($epley, 1),
            'brzycki_1rm'    => round($brzycki, 1),
            'input_reps'     => $reps,
            'formula_used'   => $formula,
            'percentage_table' => [
                ['percentage' => 100, 'weight' => $rm,                'reps' => '1'],
                ['percentage' => 95,  'weight' => round($rm * 0.95, 1), 'reps' => '2-3'],
                ['percentage' => 90,  'weight' => round($rm * 0.90, 1), 'reps' => '3-5'],
                ['percentage' => 85,  'weight' => round($rm * 0.85, 1), 'reps' => '5-7'],
                ['percentage' => 80,  'weight' => round($rm * 0.80, 1), 'reps' => '7-9'],
                ['percentage' => 75,  'weight' => round($rm * 0.75, 1), 'reps' => '9-12'],
                ['percentage' => 70,  'weight' => round($rm * 0.70, 1), 'reps' => '12-15'],
                ['percentage' => 65,  'weight' => round($rm * 0.65, 1), 'reps' => '15-20'],
            ],
        ];
    }
}
