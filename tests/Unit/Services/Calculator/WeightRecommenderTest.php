<?php

namespace Tests\Unit\Services\Calculator;

use App\Services\Calculator\WeightRecommender;
use PHPUnit\Framework\TestCase;

class WeightRecommenderTest extends TestCase
{
    public function test_hypertrophy_recommendation(): void
    {
        $result = WeightRecommender::recommend([
            'estimated_1rm' => 100, 'training_goal' => 'hypertrophy',
        ]);

        // hypertrophy: 67-82% → mid ~74.5% → 74.5kg → floor to 72.5
        $this->assertGreaterThanOrEqual(65, $result['recommended_weight']);
        $this->assertLessThanOrEqual(85, $result['recommended_weight']);
        $this->assertEquals(100.0, $result['estimated_1rm']);
        $this->assertEquals('hypertrophy', $result['training_goal']);
    }

    public function test_strength_recommendation_higher_weight(): void
    {
        $result = WeightRecommender::recommend([
            'estimated_1rm' => 100, 'training_goal' => 'strength',
        ]);

        // strength: 85-95% → recommended should be higher
        $this->assertGreaterThanOrEqual(82.5, $result['recommended_weight']);
    }

    public function test_weight_rounded_to_2_5kg(): void
    {
        $result = WeightRecommender::recommend([
            'estimated_1rm' => 100, 'training_goal' => 'hypertrophy',
        ]);

        // Must be a multiple of 2.5
        $this->assertEquals(0, fmod($result['recommended_weight'], 2.5));
        $this->assertEquals(0, fmod($result['weight_range']['min'], 2.5));
        $this->assertEquals(0, fmod($result['weight_range']['max'], 2.5));
    }

    public function test_rpe_based_recommendation(): void
    {
        $result = WeightRecommender::recommend([
            'estimated_1rm' => 100, 'training_goal' => 'hypertrophy',
            'target_rpe' => 8,
        ]);

        // RPE 8 → 88-90% range, should influence recommendation
        $this->assertArrayHasKey('recommended_weight', $result);
        $this->assertGreaterThan(0, $result['recommended_weight']);
    }

    public function test_result_includes_rest_seconds(): void
    {
        $result = WeightRecommender::recommend([
            'estimated_1rm' => 100, 'training_goal' => 'strength',
        ]);

        $this->assertArrayHasKey('rest_seconds', $result);
        $this->assertCount(2, $result['rest_seconds']);
    }

    public function test_endurance_lower_weight(): void
    {
        $strength = WeightRecommender::recommend([
            'estimated_1rm' => 100, 'training_goal' => 'strength',
        ]);
        $endurance = WeightRecommender::recommend([
            'estimated_1rm' => 100, 'training_goal' => 'endurance',
        ]);

        $this->assertLessThan($strength['recommended_weight'], $endurance['recommended_weight']);
    }
}
