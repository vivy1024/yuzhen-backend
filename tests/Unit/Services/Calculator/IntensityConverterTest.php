<?php

namespace Tests\Unit\Services\Calculator;

use App\Services\Calculator\IntensityConverter;
use PHPUnit\Framework\TestCase;

class IntensityConverterTest extends TestCase
{
    public function test_rpe_10_is_max_effort(): void
    {
        $result = IntensityConverter::convert([
            'input_type' => 'rpe', 'value' => 10,
        ]);

        $this->assertEquals(10.0, $result['rpe']);
        $this->assertEquals(0, $result['rir']);
        $this->assertEquals(100, $result['percentage_low']);
        $this->assertEquals(100, $result['percentage_high']);
    }

    public function test_rpe_8_has_2_rir(): void
    {
        $result = IntensityConverter::convert([
            'input_type' => 'rpe', 'value' => 8,
        ]);

        $this->assertEquals(8.0, $result['rpe']);
        $this->assertEquals(2, $result['rir']);
        $this->assertEquals(88, $result['percentage_low']);
        $this->assertEquals(90, $result['percentage_high']);
    }

    public function test_rir_to_rpe_conversion(): void
    {
        // RIR 1 → RPE 9
        $result = IntensityConverter::convert([
            'input_type' => 'rir', 'value' => 1,
        ]);

        $this->assertEquals(9.0, $result['rpe']);
        $this->assertEquals(1, $result['rir']);
    }

    public function test_percentage_to_rpe(): void
    {
        // 95% → should map to RPE ~9.5
        $result = IntensityConverter::convert([
            'input_type' => 'percentage', 'value' => 95,
        ]);

        $this->assertArrayHasKey('rpe', $result);
        $this->assertArrayHasKey('rir', $result);
        $this->assertGreaterThanOrEqual(9, $result['rpe']);
    }

    public function test_rpe_clamped_to_6_10(): void
    {
        // RPE 5 should be clamped to 6
        $result = IntensityConverter::convert([
            'input_type' => 'rpe', 'value' => 5,
        ]);

        $this->assertEquals(6.0, $result['rpe']);
    }

    public function test_description_present(): void
    {
        $result = IntensityConverter::convert([
            'input_type' => 'rpe', 'value' => 8,
        ]);

        $this->assertArrayHasKey('description', $result);
        $this->assertNotEmpty($result['description']);
    }
}
