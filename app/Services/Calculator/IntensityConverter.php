<?php

namespace App\Services\Calculator;

/**
 * 训练强度转换器
 * RPE ↔ RIR ↔ %1RM 三向转换
 */
class IntensityConverter
{
    /**
     * RPE → RIR → %1RM 对照表
     * RPE 6-10 对应 RIR 4-0
     */
    private const RPE_TABLE = [
        // RPE => [RIR, %1RM 范围 (低次数, 高次数)]
        10   => ['rir' => 0,   'pct_low' => 100, 'pct_high' => 100],
        9.5  => ['rir' => 0.5, 'pct_low' => 97,  'pct_high' => 98],
        9    => ['rir' => 1,   'pct_low' => 94,  'pct_high' => 96],
        8.5  => ['rir' => 1.5, 'pct_low' => 91,  'pct_high' => 93],
        8    => ['rir' => 2,   'pct_low' => 88,  'pct_high' => 90],
        7.5  => ['rir' => 2.5, 'pct_low' => 85,  'pct_high' => 87],
        7    => ['rir' => 3,   'pct_low' => 82,  'pct_high' => 84],
        6.5  => ['rir' => 3.5, 'pct_low' => 79,  'pct_high' => 81],
        6    => ['rir' => 4,   'pct_low' => 75,  'pct_high' => 78],
    ];

    /**
     * 三向转换
     *
     * @param array $params [input_type: 'rpe'|'rir'|'percentage', value: float, reps?: int]
     * @return array
     */
    public static function convert(array $params): array
    {
        $inputType = $params['input_type'];
        $value = (float) $params['value'];

        return match ($inputType) {
            'rpe'        => self::fromRPE($value),
            'rir'        => self::fromRIR($value),
            'percentage' => self::fromPercentage($value),
            default      => ['error' => "不支持的输入类型: {$inputType}"],
        };
    }

    /**
     * RPE → RIR + %1RM
     */
    private static function fromRPE(float $rpe): array
    {
        $rpe = max(6, min(10, $rpe));
        // 四舍五入到最近的 0.5
        $rpeKey = round($rpe * 2) / 2;

        $entry = self::RPE_TABLE[$rpeKey] ?? self::interpolate($rpeKey);

        return [
            'rpe'            => $rpeKey,
            'rir'            => $entry['rir'],
            'percentage_low' => $entry['pct_low'],
            'percentage_high' => $entry['pct_high'],
            'description'    => self::describeRPE($rpeKey),
        ];
    }

    /**
     * RIR → RPE + %1RM
     */
    private static function fromRIR(float $rir): array
    {
        $rir = max(0, min(4, $rir));
        $rpe = 10 - $rir;

        return self::fromRPE($rpe);
    }

    /**
     * %1RM → RPE + RIR
     */
    private static function fromPercentage(float $pct): array
    {
        $pct = max(75, min(100, $pct));

        // 找到最接近的 RPE 条目
        $bestRPE = 8;
        $bestDiff = PHP_FLOAT_MAX;

        foreach (self::RPE_TABLE as $rpe => $entry) {
            $mid = ($entry['pct_low'] + $entry['pct_high']) / 2;
            $diff = abs($mid - $pct);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $bestRPE = $rpe;
            }
        }

        $entry = self::RPE_TABLE[$bestRPE];

        return [
            'rpe'            => (float) $bestRPE,
            'rir'            => $entry['rir'],
            'percentage_low' => $entry['pct_low'],
            'percentage_high' => $entry['pct_high'],
            'input_percentage' => $pct,
            'description'    => self::describeRPE((float) $bestRPE),
        ];
    }

    /**
     * 插值计算（处理非标准 RPE 值）
     */
    private static function interpolate(float $rpe): array
    {
        $lower = floor($rpe * 2) / 2;
        $upper = ceil($rpe * 2) / 2;

        $lEntry = self::RPE_TABLE[$lower] ?? self::RPE_TABLE[6];
        $uEntry = self::RPE_TABLE[$upper] ?? self::RPE_TABLE[10];

        $ratio = ($upper - $lower) > 0 ? ($rpe - $lower) / ($upper - $lower) : 0;

        return [
            'rir'      => round($lEntry['rir'] + ($uEntry['rir'] - $lEntry['rir']) * $ratio, 1),
            'pct_low'  => (int) round($lEntry['pct_low'] + ($uEntry['pct_low'] - $lEntry['pct_low']) * $ratio),
            'pct_high' => (int) round($lEntry['pct_high'] + ($uEntry['pct_high'] - $lEntry['pct_high']) * $ratio),
        ];
    }

    /**
     * RPE 描述
     */
    private static function describeRPE(float $rpe): string
    {
        return match (true) {
            $rpe >= 10  => '力竭 — 无法再完成一次',
            $rpe >= 9   => '非常吃力 — 还能做1次',
            $rpe >= 8   => '吃力 — 还能做2次',
            $rpe >= 7   => '有挑战 — 还能做3次',
            default     => '中等强度 — 还能做4+次',
        };
    }
}
