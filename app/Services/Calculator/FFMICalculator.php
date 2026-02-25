<?php

namespace App\Services\Calculator;

/**
 * FFMI 计算器
 * BMI + FFMI + 标准化FFMI + 评级 + 自然潜力评估
 * 统一 ProgressRecord::calculateFFMI 和前端 ffmi-calculator.ts 的逻辑
 */
class FFMICalculator
{
    /** 男性 FFMI 评级阈值 */
    private const MALE_THRESHOLDS = [
        'low'        => 17,
        'average'    => 19,
        'good'       => 21,
        'excellent'  => 23,
        'elite'      => 25,
        'suspicious' => 27,
    ];

    /** 女性 FFMI 评级阈值 */
    private const FEMALE_THRESHOLDS = [
        'low'        => 14,
        'average'    => 16,
        'good'       => 18,
        'excellent'  => 20,
        'elite'      => 22,
        'suspicious' => 24,
    ];

    /** 自然极限 FFMI */
    private const NATURAL_LIMIT = [
        'male'   => 25,
        'female' => 22,
    ];

    /**
     * 完整 FFMI 计算
     *
     * @param array $params [height_cm, weight_kg, gender, body_fat?]
     * @return array
     */
    public static function calculate(array $params): array
    {
        $heightM = $params['height_cm'] / 100;
        $weightKg = $params['weight_kg'];
        $gender = $params['gender'];

        // BMI
        $bmi = $weightKg / ($heightM * $heightM);
        $bmiStatus = self::getBMIStatus($bmi);

        // 体脂率：用户提供 or BMI 估算
        $usedEstimatedBf = !isset($params['body_fat']) || $params['body_fat'] === null;
        $bodyFat = $usedEstimatedBf
            ? self::estimateBodyFat($bmi, $gender)
            : (float) $params['body_fat'];

        // 瘦体重
        $leanBodyMass = $weightKg * (1 - $bodyFat / 100);

        // FFMI
        $ffmi = $leanBodyMass / ($heightM * $heightM);

        // 标准化 FFMI（调整到 1.8m 身高）
        $normalizedFFMI = $ffmi + 6.1 * (1.8 - $heightM);

        // 评级和建议
        $assessment = self::getAssessment($normalizedFFMI, $gender);
        $naturalPotential = self::getNaturalPotential($normalizedFFMI, $gender);
        $trainingRecommendation = self::getTrainingRecommendation($normalizedFFMI, $bmi, $gender);

        return [
            'bmi'                     => round($bmi, 1),
            'bmi_status'              => $bmiStatus,
            'body_fat'                => round($bodyFat, 1),
            'lean_body_mass'          => round($leanBodyMass, 1),
            'ffmi'                    => round($ffmi, 1),
            'normalized_ffmi'         => round($normalizedFFMI, 1),
            'assessment'              => $assessment,
            'natural_potential'       => $naturalPotential,
            'training_recommendation' => $trainingRecommendation,
            'used_estimated_bf'       => $usedEstimatedBf,
        ];
    }

    /**
     * 简化版 FFMI 计算（兼容 ProgressRecord 调用）
     */
    public static function calculateSimple(float $weight, float $bodyFat, float $heightCm): array
    {
        $heightM = $heightCm / 100;
        $leanBodyMass = $weight * (1 - $bodyFat / 100);
        $ffmi = $leanBodyMass / ($heightM * $heightM);

        return [
            'ffmi'           => round($ffmi, 2),
            'lean_body_mass' => round($leanBodyMass, 2),
        ];
    }

    /**
     * BMI 分类（中国标准）
     */
    private static function getBMIStatus(float $bmi): string
    {
        if ($bmi < 18.5) return 'underweight';
        if ($bmi < 24)   return 'normal';
        if ($bmi < 28)   return 'overweight';
        return 'obese';
    }

    /**
     * 基于 BMI 估算体脂率
     */
    private static function estimateBodyFat(float $bmi, string $gender): float
    {
        if ($gender === 'male') {
            return max(5, min(40, (1.20 * $bmi) - 10.8 - 5.4));
        }
        return max(10, min(45, (1.20 * $bmi) - 10.8 + 5.4));
    }

    /**
     * FFMI 评级
     */
    private static function getAssessment(float $ffmi, string $gender): string
    {
        $t = $gender === 'male' ? self::MALE_THRESHOLDS : self::FEMALE_THRESHOLDS;

        if ($ffmi < $t['low'])        return '偏低';
        if ($ffmi < $t['average'])    return '一般';
        if ($ffmi < $t['good'])       return '良好';
        if ($ffmi < $t['excellent'])  return '优秀';
        if ($ffmi < $t['elite'])      return '很好';
        if ($ffmi < $t['suspicious']) return '极佳';
        return '疑似使用增强剂';
    }

    /**
     * 自然潜力评估
     */
    private static function getNaturalPotential(float $ffmi, string $gender): array
    {
        $limit = self::NATURAL_LIMIT[$gender] ?? 25;
        $percentage = ($ffmi / $limit) * 100;

        $description = match (true) {
            $percentage < 60 => '还有很大提升空间',
            $percentage < 75 => '有较大提升空间',
            $percentage < 85 => '有一定提升空间',
            $percentage < 95 => '接近自然极限',
            default          => '已达到或超过自然极限',
        };

        return [
            'percentage'  => round($percentage, 1),
            'limit'       => $limit,
            'description' => $description,
        ];
    }

    /**
     * 训练建议
     */
    private static function getTrainingRecommendation(float $ffmi, float $bmi, string $gender): array
    {
        $limit = self::NATURAL_LIMIT[$gender] ?? 25;
        $percentage = ($ffmi / $limit) * 100;

        if ($bmi < 18.5) {
            return [
                'focus'       => '增肌增重',
                'suggestions' => [
                    '增加热量摄入，每日热量盈余300-500kcal',
                    '保证充足蛋白质摄入（1.6-2.2g/kg体重）',
                    '以复合动作为主，逐步增加训练重量',
                    '保证充足睡眠和恢复时间',
                ],
            ];
        }

        if ($bmi > 28) {
            return [
                'focus'       => '减脂保肌',
                'suggestions' => [
                    '适度热量缺口，每日减少300-500kcal',
                    '保持高蛋白摄入防止肌肉流失',
                    '结合力量训练和有氧运动',
                    '注意训练强度，避免过度疲劳',
                ],
            ];
        }

        if ($percentage < 70) {
            return [
                'focus'       => '增肌为主',
                'suggestions' => [
                    '专注于渐进式超负荷训练',
                    '保证充足蛋白质摄入',
                    '合理安排训练和休息',
                    '可以适当增加训练频率',
                ],
            ];
        }

        return [
            'focus'       => '维持优化',
            'suggestions' => [
                '保持当前训练强度',
                '注重训练质量而非数量',
                '关注薄弱肌群的发展',
                '定期调整训练计划避免平台期',
            ],
        ];
    }
}
