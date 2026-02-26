<?php

namespace Tests\Unit\Services\Calculator;

use App\Services\Calculator\MacroCalculator;
use PHPUnit\Framework\TestCase;

class MacroCalculatorTest extends TestCase
{
    public function test_balanced_method_default(): void
    {
        $result = MacroCalculator::calculate([
            'target_calories' => 2500, 'weight_kg' => 70,
            'fitness_goal' => 'maintenance',
        ]);

        $this->assertEquals('balanced', $result['method']);
        $this->assertEquals('maintenance', $result['fitness_goal']);
        $this->assertEquals(2500, $result['target_calories']);
    }

    public function test_macros_sum_approximately_equals_total(): void
    {
        $result = MacroCalculator::calculate([
            'target_calories' => 2500, 'weight_kg' => 70,
            'fitness_goal' => 'maintenance',
        ]);

        $macros = $result['macros'];
        $totalCal = $macros['protein_cal'] + $macros['carbs_cal'] + $macros['fat_cal'];
        $this->assertEqualsWithDelta($result['total_calories'], $totalCal, 5);
    }

    public function test_ratios_sum_to_100(): void
    {
        $result = MacroCalculator::calculate([
            'target_calories' => 2500, 'weight_kg' => 70,
            'fitness_goal' => 'maintenance',
        ]);

        $ratioSum = $result['ratios']['protein'] + $result['ratios']['carbs'] + $result['ratios']['fat'];
        $this->assertEqualsWithDelta(100, $ratioSum, 1);
    }

    public function test_body_weight_method(): void
    {
        $result = MacroCalculator::calculate([
            'target_calories' => 2500, 'weight_kg' => 70,
            'fitness_goal' => 'hypertrophy', 'method' => 'body_weight',
        ]);

        $this->assertEquals('body_weight', $result['method']);
        // Protein should be ~2.0 g/kg for hypertrophy
        $this->assertEqualsWithDelta(2.0, $result['per_kg']['protein'], 0.1);
    }

    public function test_ratio_method(): void
    {
        $result = MacroCalculator::calculate([
            'target_calories' => 2000, 'weight_kg' => 60,
            'fitness_goal' => 'fat_loss', 'method' => 'ratio',
        ]);

        $this->assertEquals('ratio', $result['method']);
        // fat_loss ratio: 40/30/30
        $this->assertEqualsWithDelta(40, $result['ratios']['protein'], 1);
        $this->assertEqualsWithDelta(30, $result['ratios']['carbs'], 1);
        $this->assertEqualsWithDelta(30, $result['ratios']['fat'], 1);
    }

    public function test_per_kg_values_present(): void
    {
        $result = MacroCalculator::calculate([
            'target_calories' => 2500, 'weight_kg' => 70,
            'fitness_goal' => 'maintenance',
        ]);

        $this->assertArrayHasKey('protein', $result['per_kg']);
        $this->assertArrayHasKey('carbs', $result['per_kg']);
        $this->assertArrayHasKey('fat', $result['per_kg']);
        $this->assertGreaterThan(0, $result['per_kg']['protein']);
    }

    public function test_fat_loss_higher_protein_per_kg(): void
    {
        $fatLoss = MacroCalculator::calculate([
            'target_calories' => 2000, 'weight_kg' => 70,
            'fitness_goal' => 'fat_loss',
        ]);
        $maintenance = MacroCalculator::calculate([
            'target_calories' => 2500, 'weight_kg' => 70,
            'fitness_goal' => 'maintenance',
        ]);

        // fat_loss protein per kg (2.2) > maintenance (1.8)
        $this->assertGreaterThan(
            $maintenance['per_kg']['protein'],
            $fatLoss['per_kg']['protein']
        );
    }
}
