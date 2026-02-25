<?php

namespace Tests\Unit\Services\Calculator;

use App\Services\Calculator\OneRMCalculator;
use PHPUnit\Framework\TestCase;

class OneRMCalculatorTest extends TestCase
{
    public function test_single_rep_returns_actual_weight(): void
    {
        $result = OneRMCalculator::calculate([
            'weight_kg' => 100, 'reps' => 1,
        ]);

        $this->assertEquals(100.0, $result['estimated_1rm']);
        $this->assertEquals('direct', $result['formula_used']);
    }

    public function test_epley_formula(): void
    {
        // Epley: 1RM = 80 * (1 + 10/30) = 80 * 1.333 = 106.7
        $result = OneRMCalculator::calculate([
            'weight_kg' => 80, 'reps' => 10, 'formula' => 'epley',
        ]);

        $this->assertEqualsWithDelta(106.7, $result['estimated_1rm'], 0.1);
        $this->assertEquals('epley', $result['formula_used']);
    }

    public function test_brzycki_formula(): void
    {
        // Brzycki: 1RM = 80 * 36 / (37 - 10) = 80 * 36/27 = 106.7
        $result = OneRMCalculator::calculate([
            'weight_kg' => 80, 'reps' => 10, 'formula' => 'brzycki',
        ]);

        $this->assertEqualsWithDelta(106.7, $result['estimated_1rm'], 0.1);
    }

    public function test_average_formula_is_default(): void
    {
        $result = OneRMCalculator::calculate([
            'weight_kg' => 80, 'reps' => 5,
        ]);

        $this->assertEquals('average', $result['formula_used']);
        // average = (epley + brzycki) / 2
        $epley = 80 * (1 + 5 / 30);
        $brzycki = 80 * 36 / (37 - 5);
        $expected = round(($epley + $brzycki) / 2, 1);
        $this->assertEqualsWithDelta($expected, $result['estimated_1rm'], 0.1);
    }

    public function test_percentage_table_has_8_entries(): void
    {
        $result = OneRMCalculator::calculate([
            'weight_kg' => 100, 'reps' => 5,
        ]);

        $this->assertCount(8, $result['percentage_table']);
        $this->assertEquals(100, $result['percentage_table'][0]['percentage']);
        $this->assertEquals(65, $result['percentage_table'][7]['percentage']);
    }

    public function test_zero_reps_returns_direct(): void
    {
        $result = OneRMCalculator::calculate([
            'weight_kg' => 100, 'reps' => 0,
        ]);

        $this->assertEquals(100.0, $result['estimated_1rm']);
        $this->assertEquals('direct', $result['formula_used']);
    }

    public function test_batch_calculation(): void
    {
        $result = OneRMCalculator::calculateBatch([
            ['name' => 'bench_press', 'weight_kg' => 80, 'reps' => 5],
            ['name' => 'squat', 'weight_kg' => 120, 'reps' => 3],
        ]);

        $this->assertCount(2, $result['exercises']);
        $this->assertEquals('bench_press', $result['exercises'][0]['exercise']);
        $this->assertEquals('squat', $result['exercises'][1]['exercise']);
    }
}
