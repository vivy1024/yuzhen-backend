<?php

namespace Database\Factories;

use App\Models\TrainingLog;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * TrainingLog Factory - 训练日志工厂
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrainingLog>
 */
class TrainingLogFactory extends Factory
{
    protected $model = TrainingLog::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'planned_exercises' => $this->generatePlannedExercises(),
            'actual_exercises' => $this->generateActualExercises(),
            'completion_rate' => fake()->randomFloat(2, 0.5, 1.0),
            'avg_rpe' => fake()->randomFloat(1, 5.0, 9.0),
            'week_number' => fake()->numberBetween(1, 12),
            'mesocycle_id' => 'meso_' . fake()->uuid(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * 生成计划动作列表
     */
    private function generatePlannedExercises(): array
    {
        $exercises = [
            ['id' => 'squat', 'name' => '深蹲'],
            ['id' => 'bench_press', 'name' => '卧推'],
            ['id' => 'deadlift', 'name' => '硬拉'],
            ['id' => 'overhead_press', 'name' => '推举'],
            ['id' => 'barbell_row', 'name' => '杠铃划船'],
        ];

        $count = fake()->numberBetween(3, 5);
        $selected = fake()->randomElements($exercises, $count);

        return array_map(function ($exercise) {
            return [
                'exercise_id' => $exercise['id'],
                'exercise_name' => $exercise['name'],
                'sets' => fake()->numberBetween(3, 5),
                'reps' => fake()->numberBetween(6, 12),
                'weight' => fake()->numberBetween(20, 100),
            ];
        }, $selected);
    }

    /**
     * 生成实际完成情况
     */
    private function generateActualExercises(): array
    {
        $exercises = $this->generatePlannedExercises();

        return array_map(function ($exercise) {
            $plannedSets = $exercise['sets'];
            $completedSets = fake()->numberBetween(max(1, $plannedSets - 1), $plannedSets);

            return [
                'exercise_id' => $exercise['exercise_id'],
                'completed_sets' => $completedSets,
                'rpe' => fake()->randomFloat(1, 6.0, 9.5),
                'weight' => $exercise['weight'],
            ];
        }, $exercises);
    }

    /**
     * 设置为完全完成的训练
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $planned = $attributes['planned_exercises'] ?? $this->generatePlannedExercises();
            $actual = array_map(function ($exercise) {
                return [
                    'exercise_id' => $exercise['exercise_id'],
                    'completed_sets' => $exercise['sets'],
                    'rpe' => fake()->randomFloat(1, 6.0, 8.0),
                    'weight' => $exercise['weight'],
                ];
            }, $planned);

            return [
                'planned_exercises' => $planned,
                'actual_exercises' => $actual,
                'completion_rate' => 1.0,
            ];
        });
    }

    /**
     * 设置为部分完成的训练
     */
    public function partial(): static
    {
        return $this->state(function (array $attributes) {
            $planned = $attributes['planned_exercises'] ?? $this->generatePlannedExercises();
            $actual = array_map(function ($exercise) {
                $completedSets = max(1, $exercise['sets'] - fake()->numberBetween(1, 2));
                return [
                    'exercise_id' => $exercise['exercise_id'],
                    'completed_sets' => $completedSets,
                    'rpe' => fake()->randomFloat(1, 8.0, 9.5),
                    'weight' => $exercise['weight'],
                ];
            }, $planned);

            return [
                'planned_exercises' => $planned,
                'actual_exercises' => $actual,
            ];
        });
    }

    /**
     * 设置高RPE训练
     */
    public function highRpe(): static
    {
        return $this->state(function (array $attributes) {
            $actual = $attributes['actual_exercises'] ?? $this->generateActualExercises();
            $actual = array_map(function ($exercise) {
                $exercise['rpe'] = fake()->randomFloat(1, 9.0, 10.0);
                return $exercise;
            }, $actual);

            return [
                'actual_exercises' => $actual,
                'avg_rpe' => fake()->randomFloat(1, 9.0, 10.0),
            ];
        });
    }

    /**
     * 设置低RPE训练
     */
    public function lowRpe(): static
    {
        return $this->state(function (array $attributes) {
            $actual = $attributes['actual_exercises'] ?? $this->generateActualExercises();
            $actual = array_map(function ($exercise) {
                $exercise['rpe'] = fake()->randomFloat(1, 5.0, 7.0);
                return $exercise;
            }, $actual);

            return [
                'actual_exercises' => $actual,
                'avg_rpe' => fake()->randomFloat(1, 5.0, 7.0),
            ];
        });
    }
}
