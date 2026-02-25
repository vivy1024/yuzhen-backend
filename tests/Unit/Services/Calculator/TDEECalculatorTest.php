<?php

namespace Tests\Unit\Services\Calculator;

use App\Services\Calculator\TDEECalculator;
use PHPUnit\Framework\TestCase;

class TDEECalculatorTest extends TestCase
{
    public function test_male_bmr_mifflin_st_jeor(): void
    {
        // 男性 25岁 70kg 175cm 久坐 维持
        $result = TDEECalculator::calculate([
            'age' => 25, 'gender' => 'male',
            'weight_kg' => 70, 'height_cm' => 175,
            'activity_level' => 'sedentary', 'fitness_goal' => 'maintenance',
        ]);

        // BMR = 10*70 + 6.25*175 - 5*25 + 5 = 700 + 1093.75 - 125 + 5 = 1673.75 ≈ 1674
        $this->assertEquals(1674, $result['bmr']);
        $this->assertEquals('mifflin_st_jeor', $result['formula_used']);
    }

    public function test_female_bmr(): void
    {
        // 女性 30岁 55kg 160cm
        $result = TDEECalculator::calculate([
            'age' => 30, 'gender' => 'female',
            'weight_kg' => 55, 'height_cm' => 160,
            'activity_level' => 'sedentary', 'fitness_goal' => 'maintenance',
        ]);

        // BMR = 10*55 + 6.25*160 - 5*30 - 161 = 550 + 1000 - 150 - 161 = 1239
        $this->assertEquals(1239, $result['bmr']);
    }

    public function test_tdee_with_activity_multiplier(): void
    {
        $result = TDEECalculator::calculate([
            'age' => 25, 'gender' => 'male',
            'weight_kg' => 70, 'height_cm' => 175,
            'activity_level' => 'very_active', 'fitness_goal' => 'maintenance',
        ]);

        // TDEE = 1673.75 * 1.725 ≈ 2887
        $this->assertEquals(2887, $result['tdee']);
        $this->assertEquals(2887, $result['target_calories']); // maintenance = 0%
    }

    public function test_fat_loss_goal_reduces_calories(): void
    {
        $result = TDEECalculator::calculate([
            'age' => 25, 'gender' => 'male',
            'weight_kg' => 70, 'height_cm' => 175,
            'activity_level' => 'moderately_active', 'fitness_goal' => 'fat_loss',
        ]);

        // TDEE = 1673.75 * 1.55 ≈ 2594
        // target = 2594 * 0.80 ≈ 2075
        $this->assertLessThan($result['tdee'], $result['target_calories']);
        $this->assertLessThan(0, $result['deficit_or_surplus']);
    }

    public function test_hypertrophy_goal_increases_calories(): void
    {
        $result = TDEECalculator::calculate([
            'age' => 25, 'gender' => 'male',
            'weight_kg' => 70, 'height_cm' => 175,
            'activity_level' => 'moderately_active', 'fitness_goal' => 'hypertrophy',
        ]);

        $this->assertGreaterThan($result['tdee'], $result['target_calories']);
        $this->assertGreaterThan(0, $result['deficit_or_surplus']);
    }

    public function test_macros_sum_approximately_equals_target(): void
    {
        $result = TDEECalculator::calculate([
            'age' => 25, 'gender' => 'male',
            'weight_kg' => 70, 'height_cm' => 175,
            'activity_level' => 'moderately_active', 'fitness_goal' => 'maintenance',
        ]);

        $macros = $result['macros'];
        $totalCal = $macros['protein_cal'] + $macros['fat_cal'] + $macros['carbs_cal'];
        // 允许四舍五入误差 ±5 kcal
        $this->assertEqualsWithDelta($result['target_calories'], $totalCal, 5);
    }

    public function test_result_contains_all_required_keys(): void
    {
        $result = TDEECalculator::calculate([
            'age' => 25, 'gender' => 'male',
            'weight_kg' => 70, 'height_cm' => 175,
            'activity_level' => 'sedentary', 'fitness_goal' => 'maintenance',
        ]);

        $this->assertArrayHasKey('bmr', $result);
        $this->assertArrayHasKey('tdee', $result);
        $this->assertArrayHasKey('target_calories', $result);
        $this->assertArrayHasKey('deficit_or_surplus', $result);
        $this->assertArrayHasKey('macros', $result);
        $this->assertArrayHasKey('formula_used', $result);
    }
}
