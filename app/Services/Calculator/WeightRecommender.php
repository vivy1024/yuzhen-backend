<?php

namespace App\Services\Calculator;

/**
 * 训练重量推荐器
 * 基于 1RM + 训练目标 + RPE + 次数 → 推荐重量和范围
 */
class WeightRecommender
{
    /** 训练目标对应的 %1RM 范围和次数 */
    private const GOAL_PROFILES = [
        'strength' => [
            'percentage_range' => [85, 95],
            'rep_range'        => [1, 5],
            'rest_seconds'     => [180, 300],
            'description'      => '力量训练 — 高重量低次数',
        ],
        'hypertrophy' => [
            'percentage_range' => [67, 82],
            'rep_range'        => [6, 12],
            'rest_seconds'     => [60, 120],
            'description'      => '增肌训练 — 中等重量中等次数',
        ],
        'endurance' => [
            'percentage_range' => [50, 65],
            'rep_range'        => [15, 25],
            'rest_seconds'     => [30, 60],
            'description'      => '耐力训练 — 低重量高次数',
        ],
        'power' => [
            'percentage_range' => [75, 90],
            'rep_range'        => [1, 5],
            'rest_seconds'     => [180, 300],
            'description'      => '爆发力训练 — 快速发力',
        ],
    ];

    /**
     * 推荐训练重量
     *
     * @param array $params [estimated_1rm, training_goal, target_reps?, target_rpe?]
     * @return array
     */
    public static function recommend(array $params): array
    {
        $oneRM = (float) $params['estimated_1rm'];
        $goal = $params['training_goal'] ?? 'hypertrophy';
        $targetReps = $params['target_reps'] ?? null;
        $targetRPE = $params['target_rpe'] ?? null;

        $profile = self::GOAL_PROFILES[$goal] ?? self::GOAL_PROFILES['hypertrophy'];

        // 如果指定了 RPE，用 RPE 推算百分比
        if ($targetRPE !== null) {
            $intensity = IntensityConverter::convert([
                'input_type' => 'rpe',
                'value'      => $targetRPE,
            ]);
            $pctLow = $intensity['percentage_low'];
            $pctHigh = $intensity['percentage_high'];
        } else {
            $pctLow = $profile['percentage_range'][0];
            $pctHigh = $profile['percentage_range'][1];
        }

        // 如果指定了目标次数，微调百分比
        if ($targetReps !== null) {
            $repBasedPct = self::repsToPercentage($targetReps);
            // 取 RPE/目标范围 和 次数推算的交集中点
            $pctMid = ($pctLow + $pctHigh + $repBasedPct) / 3;
        } else {
            $pctMid = ($pctLow + $pctHigh) / 2;
        }

        $recommendedWeight = $oneRM * $pctMid / 100;

        // 向下取整到 2.5kg 的倍数（杠铃片最小单位）
        $roundedWeight = floor($recommendedWeight / 2.5) * 2.5;

        return [
            'recommended_weight' => $roundedWeight,
            'weight_range'       => [
                'min' => floor($oneRM * $pctLow / 100 / 2.5) * 2.5,
                'max' => floor($oneRM * $pctHigh / 100 / 2.5) * 2.5,
            ],
            'estimated_1rm'      => $oneRM,
            'percentage_of_1rm'  => round($pctMid, 1),
            'training_goal'      => $goal,
            'rep_range'          => $profile['rep_range'],
            'rest_seconds'       => $profile['rest_seconds'],
            'description'        => $profile['description'],
        ];
    }

    /**
     * 次数 → 大致 %1RM 映射
     */
    private static function repsToPercentage(int $reps): float
    {
        // Epley 反推: %1RM = 100 / (1 + reps/30)
        return 100 / (1 + $reps / 30);
    }
}
