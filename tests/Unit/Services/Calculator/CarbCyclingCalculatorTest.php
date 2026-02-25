<?php

namespace Tests\Unit\Services\Calculator;

use App\Services\Calculator\CarbCyclingCalculator;
use PHPUnit\Framework\TestCase;

class CarbCyclingCalculatorTest extends TestCase
{
    public function test_weekly_plan_has_7_days(): void
    {
        $result = CarbCyclingCalculator::calculate([
            'tdee' => 2500, 'weight_kg' => 70,
            'fitness_goal' => 'maintenance', 'training_days' => [0, 2, 4],
        ]);

        $this->assertCount(7, $result['weekly_plan']);
    }

    public function test_training_days_are_high_carb(): void
    {
        $result = CarbCyclingCalculator::calculate([
            'tdee' => 2500, 'weight_kg' => 70,
            'fitness_goal' => 'maintenance', 'training_days' => [0, 2, 4],
        ]);

        // Days 0, 2, 4 should be high carb
        $this->assertEquals('high', $result['weekly_plan'][0]['carb_type']);
        $this->assertEquals('high', $result['weekly_plan'][2]['carb_type']);
        $this->assertEquals('high', $result['weekly_plan'][4]['carb_type']);
        $this->assertEquals(3, $result['high_carb_days']);
    }

    public function test_protein_is_constant_across_days(): void
    {
        $result = CarbCyclingCalculator::calculate([
            'tdee' => 2500, 'weight_kg' => 70,
            'fitness_goal' => 'maintenance', 'training_days' => [0, 2, 4],
        ]);

        $proteinValues = array_column($result['weekly_plan'], 'protein_g');
        // All days should have the same protein
        $this->assertCount(1, array_unique($proteinValues));
        // 70kg * 2.0 g/kg = 140g
        $this->assertEquals(140, $result['constant_protein_g']);
    }

    public function test_fat_loss_rest_days_are_low_carb(): void
    {
        $result = CarbCyclingCalculator::calculate([
            'tdee' => 2500, 'weight_kg' => 70,
            'fitness_goal' => 'fat_loss', 'training_days' => [0, 2, 4],
        ]);

        // Non-training days should be low carb for fat_loss
        $this->assertEquals('low', $result['weekly_plan'][1]['carb_type']);
        $this->assertEquals('low', $result['weekly_plan'][3]['carb_type']);
        $this->assertEquals('low', $result['weekly_plan'][5]['carb_type']);
        $this->assertEquals('low', $result['weekly_plan'][6]['carb_type']);
    }

    public function test_carb_day_counts_sum_to_7(): void
    {
        $result = CarbCyclingCalculator::calculate([
            'tdee' => 2500, 'weight_kg' => 70,
            'fitness_goal' => 'maintenance', 'training_days' => [0, 2, 4],
        ]);

        $total = $result['high_carb_days'] + $result['medium_carb_days'] + $result['low_carb_days'];
        $this->assertEquals(7, $total);
    }

    public function test_weekly_average_matches_total(): void
    {
        $result = CarbCyclingCalculator::calculate([
            'tdee' => 2500, 'weight_kg' => 70,
            'fitness_goal' => 'maintenance', 'training_days' => [0, 2, 4],
        ]);

        $expectedAvg = (int) round($result['weekly_total_calories'] / 7);
        $this->assertEquals($expectedAvg, $result['weekly_average_calories']);
    }
}
