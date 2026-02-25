<?php

namespace Tests\Unit\Services\Calculator;

use App\Services\Calculator\FFMICalculator;
use PHPUnit\Framework\TestCase;

class FFMICalculatorTest extends TestCase
{
    public function test_male_ffmi_with_known_body_fat(): void
    {
        $result = FFMICalculator::calculate([
            'height_cm' => 175, 'weight_kg' => 80,
            'gender' => 'male', 'body_fat' => 15,
        ]);

        // LBM = 80 * 0.85 = 68, FFMI = 68 / 1.75^2 = 22.2
        $this->assertEqualsWithDelta(22.2, $result['ffmi'], 0.2);
        $this->assertFalse($result['used_estimated_bf']);
        $this->assertEquals(15.0, $result['body_fat']);
    }

    public function test_estimated_body_fat_when_not_provided(): void
    {
        $result = FFMICalculator::calculate([
            'height_cm' => 175, 'weight_kg' => 80, 'gender' => 'male',
        ]);

        $this->assertTrue($result['used_estimated_bf']);
        $this->assertGreaterThan(5, $result['body_fat']);
        $this->assertLessThan(40, $result['body_fat']);
    }

    public function test_bmi_classification_chinese_standard(): void
    {
        // BMI = 80 / 1.75^2 ≈ 26.1 → overweight (中国标准 24-28)
        $result = FFMICalculator::calculate([
            'height_cm' => 175, 'weight_kg' => 80,
            'gender' => 'male', 'body_fat' => 15,
        ]);
        $this->assertEquals('overweight', $result['bmi_status']);

        // BMI = 60 / 1.70^2 ≈ 20.8 → normal
        $result2 = FFMICalculator::calculate([
            'height_cm' => 170, 'weight_kg' => 60,
            'gender' => 'female', 'body_fat' => 22,
        ]);
        $this->assertEquals('normal', $result2['bmi_status']);
    }

    public function test_normalized_ffmi_adjusts_for_height(): void
    {
        $result = FFMICalculator::calculate([
            'height_cm' => 175, 'weight_kg' => 80,
            'gender' => 'male', 'body_fat' => 15,
        ]);

        // normalized = ffmi + 6.1 * (1.8 - 1.75) = ffmi + 0.305
        $this->assertGreaterThan($result['ffmi'], $result['normalized_ffmi']);
    }

    public function test_natural_potential_returns_percentage(): void
    {
        $result = FFMICalculator::calculate([
            'height_cm' => 175, 'weight_kg' => 80,
            'gender' => 'male', 'body_fat' => 15,
        ]);

        $this->assertArrayHasKey('percentage', $result['natural_potential']);
        $this->assertArrayHasKey('description', $result['natural_potential']);
        $this->assertGreaterThan(0, $result['natural_potential']['percentage']);
    }

    public function test_training_recommendation_structure(): void
    {
        $result = FFMICalculator::calculate([
            'height_cm' => 175, 'weight_kg' => 80,
            'gender' => 'male', 'body_fat' => 15,
        ]);

        $this->assertArrayHasKey('focus', $result['training_recommendation']);
        $this->assertArrayHasKey('suggestions', $result['training_recommendation']);
        $this->assertIsArray($result['training_recommendation']['suggestions']);
    }

    public function test_simple_calculation_compat(): void
    {
        $result = FFMICalculator::calculateSimple(80, 15, 175);

        $this->assertArrayHasKey('ffmi', $result);
        $this->assertArrayHasKey('lean_body_mass', $result);
        $this->assertEqualsWithDelta(68.0, $result['lean_body_mass'], 0.1);
    }
}
