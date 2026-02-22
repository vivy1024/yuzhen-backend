<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\PlanTemplate;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanExercise;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * PlanTemplateController - 训练计划模板
 */
class PlanTemplateController extends BaseController
{
    /**
     * 模板列表
     * GET /api/training/templates
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PlanTemplate::where('is_active', true);

            if ($request->has('goal')) {
                $query->where('goal', $request->goal);
            }
            if ($request->has('level')) {
                $query->where('level', $request->level);
            }

            $templates = $query->orderBy('use_count', 'desc')->get();

            return $this->success($templates->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'goal' => $t->goal,
                'level' => $t->level,
                'durationWeeks' => $t->duration_weeks,
                'workoutsPerWeek' => $t->workouts_per_week,
                'exerciseCount' => is_array($t->exercises) ? count($t->exercises) : 0,
                'tags' => $t->tags,
                'useCount' => $t->use_count,
            ]), '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取模板列表');
        }
    }

    /**
     * 从模板创建个人计划
     * POST /api/training/templates/{id}/use
     */
    public function useTemplate(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $template = PlanTemplate::findOrFail($id);

            DB::beginTransaction();
            try {
                $plan = TrainingPlan::create([
                    'user_id' => $user->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'goal' => $template->goal,
                    'difficulty' => $template->level,
                    'duration_weeks' => $template->duration_weeks,
                    'workouts_per_week' => $template->workouts_per_week,
                    'type' => 'manual',
                    'is_active' => true,
                ]);

                if (is_array($template->exercises)) {
                    foreach ($template->exercises as $index => $ex) {
                        TrainingPlanExercise::create([
                            'plan_id' => $plan->id,
                            'exercise_name' => $ex['name'] ?? $ex['exercise_name'] ?? '未知动作',
                            'day_of_week' => $ex['day_of_week'] ?? null,
                            'sets' => $ex['sets'] ?? 3,
                            'reps' => $ex['reps'] ?? '8-12',
                            'weight' => $ex['weight'] ?? null,
                            'rest_time' => $ex['rest_time'] ?? '60s',
                            'notes' => $ex['notes'] ?? null,
                            'order_index' => $index,
                        ]);
                    }
                }

                $template->increment('use_count');
                DB::commit();

                return $this->success([
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'exerciseCount' => $plan->planExercises()->count(),
                ], '已从模板创建计划');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return $this->handleException($e, '使用模板创建计划');
        }
    }
}
