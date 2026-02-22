<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanExercise;
use App\Models\UserNutritionPlan;
use App\Http\Requests\UserPlanRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * TrainingPlanController - 训练计划管理
 * 
 * 提供训练计划的导入、查询、更新和删除接口
 * 支持从AI聊天中导入训练计划
 * 
 * @version 1.0.0
 * @date 2025-01-02
 */
class TrainingPlanController extends BaseController
{
    /**
     * 手动创建训练计划（含动作列表）
     * POST /api/training/plans
     */
    public function store(UserPlanRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            DB::beginTransaction();
            try {
                $plan = TrainingPlan::create([
                    'user_id' => $user->id,
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'goal' => $validated['goal'] ?? null,
                    'difficulty' => $validated['difficulty'] ?? null,
                    'duration_weeks' => $validated['duration_weeks'],
                    'workouts_per_week' => $validated['workouts_per_week'],
                    'type' => 'manual',
                    'is_active' => true,
                ]);

                $this->syncExercises($plan, $validated['exercises']);

                if (!empty($validated['nutrition'])) {
                    $this->syncNutrition($plan, $validated['nutrition']);
                }

                DB::commit();

                return $this->success([
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'exerciseCount' => $plan->planExercises()->count(),
                    'createdAt' => $plan->created_at->toIso8601String(),
                ], '创建成功');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return $this->handleException($e, '创建训练计划');
        }
    }

    /**
     * 导入训练计划
     * POST /api/training/plans/import
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'description' => 'nullable|string',
                'weeks' => 'required|integer|min:1|max:52',
                'frequency' => 'required|integer|min:1|max:7',
                'exercises' => 'required|array',
                'target_muscles' => 'nullable|array',
                'safety_notes' => 'nullable|array',
                'difficulty' => 'nullable|in:beginner,intermediate,advanced',
                'chat_session_id' => 'nullable|integer|exists:chat_sessions,id',
            ]);
            
            $user = $request->user();
            
            DB::beginTransaction();
            
            try {
                $plan = TrainingPlan::create([
                    'user_id' => $user->id,
                    'chat_session_id' => $validated['chat_session_id'] ?? null,
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'duration_weeks' => $validated['weeks'],
                    'workouts_per_week' => $validated['frequency'],
                    'exercises' => $validated['exercises'],
                    'target_muscles' => $validated['target_muscles'] ?? null,
                    'safety_notes' => $validated['safety_notes'] ?? null,
                    'difficulty' => $validated['difficulty'] ?? null,
                    'type' => 'ai_generated',
                    'is_active' => true,
                ]);
                
                DB::commit();
                
                Log::info('导入训练计划成功', [
                    'plan_id' => $plan->id,
                    'user_id' => $user->id,
                    'name' => $plan->name,
                    'chat_session_id' => $plan->chat_session_id,
                ]);
                
                return $this->success([
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'createdAt' => $plan->created_at->toIso8601String(),
                ], '导入成功');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return $this->handleException($e, '导入训练计划');
        }
    }
    
    /**
     * 获取训练计划列表
     * GET /api/training/plans
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // 支持筛选参数
            $query = TrainingPlan::where('user_id', $user->id);
            
            // 按状态筛选
            if ($request->has('status')) {
                if ($request->status === 'active') {
                    $query->where('is_active', true);
                } elseif ($request->status === 'completed') {
                    $query->whereNotNull('completed_at');
                }
            }
            
            // 按难度筛选
            if ($request->has('difficulty')) {
                $query->where('difficulty', $request->difficulty);
            }
            
            // 按目标筛选
            if ($request->has('goal')) {
                $query->where('goal', $request->goal);
            }
            
            // 按类型筛选
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            $plans = $query->withCount('planExercises')->orderBy('created_at', 'desc')->get();

            return $this->success($plans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'weeks' => $plan->duration_weeks,
                    'frequency' => $plan->workouts_per_week,
                    'difficulty' => $plan->difficulty,
                    'goal' => $plan->goal,
                    'isActive' => $plan->is_active,
                    'type' => $plan->type,
                    'exerciseCount' => $plan->plan_exercises_count ?: (is_array($plan->exercises) ? count($plan->exercises) : 0),
                    'createdAt' => $plan->created_at->toIso8601String(),
                    'startedAt' => $plan->started_at?->toIso8601String(),
                    'completedAt' => $plan->completed_at?->toIso8601String(),
                ];
            }), '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取训练计划列表');
        }
    }
    
    /**
     * 获取训练计划详情
     * GET /api/training/plans/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $plan = TrainingPlan::where('user_id', $user->id)
                ->with(['chatSession', 'planExercises.exercise', 'nutritionPlans.food'])
                ->findOrFail($id);

            return $this->success([
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'weeks' => $plan->duration_weeks,
                'frequency' => $plan->workouts_per_week,
                'exercises' => $plan->exercises,
                'planExercises' => $plan->planExercises->map(fn ($e) => [
                    'id' => $e->id,
                    'exerciseId' => $e->exercise_id,
                    'exerciseName' => $e->exercise_name,
                    'dayOfWeek' => $e->day_of_week,
                    'sets' => $e->sets,
                    'reps' => $e->reps,
                    'weight' => $e->weight,
                    'restTime' => $e->rest_time,
                    'notes' => $e->notes,
                    'orderIndex' => $e->order_index,
                ]),
                'nutritionPlans' => $plan->nutritionPlans->map(fn ($n) => [
                    'id' => $n->id,
                    'foodId' => $n->food_id,
                    'foodName' => $n->food_name,
                    'mealType' => $n->meal_type,
                    'portionGrams' => $n->portion_grams,
                    'dayOfWeek' => $n->day_of_week,
                    'notes' => $n->notes,
                    'nutrition' => $n->food ? [
                        'energyKcal' => $n->food->energy_kcal,
                        'protein' => $n->food->protein,
                        'fat' => $n->food->fat,
                        'carbohydrate' => $n->food->carbohydrate,
                    ] : null,
                ]),
                'targetMuscles' => $plan->target_muscles,
                'safetyNotes' => $plan->safety_notes,
                'difficulty' => $plan->difficulty,
                'goal' => $plan->goal,
                'isActive' => $plan->is_active,
                'type' => $plan->type,
                'createdAt' => $plan->created_at->toIso8601String(),
                'startedAt' => $plan->started_at?->toIso8601String(),
                'completedAt' => $plan->completed_at?->toIso8601String(),
                'chatSessionId' => $plan->chat_session_id,
            ], '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取训练计划详情');
        }
    }
    
    /**
     * 更新训练计划
     * PUT /api/training/plans/{id}
     */
    public function update(UserPlanRequest $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $plan = TrainingPlan::where('user_id', $user->id)
                ->findOrFail($id);

            DB::beginTransaction();
            try {
                $plan->update(collect($validated)->except(['exercises', 'nutrition'])->toArray());

                if (isset($validated['exercises'])) {
                    $this->syncExercises($plan, $validated['exercises']);
                }

                if (isset($validated['nutrition'])) {
                    $this->syncNutrition($plan, $validated['nutrition']);
                }

                DB::commit();

                return $this->success([
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'exerciseCount' => $plan->planExercises()->count(),
                    'updatedAt' => $plan->updated_at->toIso8601String(),
                ], '更新成功');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return $this->handleException($e, '更新训练计划');
        }
    }
    
    /**
     * 删除训练计划
     * DELETE /api/training/plans/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $plan = TrainingPlan::where('user_id', $user->id)
                ->findOrFail($id);
            
            $plan->delete();
            
            Log::info('删除训练计划成功', [
                'plan_id' => $id,
                'user_id' => $user->id,
            ]);
            
            return $this->success(null, '删除成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '删除训练计划');
        }
    }

    /**
     * 复制训练计划
     * POST /api/training/plans/{id}/copy
     */
    public function copy(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();

            $original = TrainingPlan::where('user_id', $user->id)
                ->with(['planExercises', 'nutritionPlans'])
                ->findOrFail($id);

            DB::beginTransaction();
            try {
                $newPlan = $original->replicate(['chat_session_id', 'started_at', 'completed_at', 'deleted_at']);
                $newPlan->name = $original->name . ' (副本)';
                $newPlan->type = 'manual';
                $newPlan->is_active = false;
                $newPlan->save();

                foreach ($original->planExercises as $exercise) {
                    $newExercise = $exercise->replicate();
                    $newExercise->plan_id = $newPlan->id;
                    $newExercise->save();
                }

                foreach ($original->nutritionPlans as $nutrition) {
                    $newNutrition = $nutrition->replicate();
                    $newNutrition->plan_id = $newPlan->id;
                    $newNutrition->save();
                }

                DB::commit();

                return $this->success([
                    'id' => $newPlan->id,
                    'name' => $newPlan->name,
                    'exerciseCount' => $newPlan->planExercises()->count(),
                    'createdAt' => $newPlan->created_at->toIso8601String(),
                ], '复制成功');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return $this->handleException($e, '复制训练计划');
        }
    }

    /**
     * 同步计划动作列表（删除旧的，插入新的）
     */
    private function syncExercises(TrainingPlan $plan, array $exercises): void
    {
        $plan->planExercises()->delete();

        foreach ($exercises as $index => $item) {
            TrainingPlanExercise::create([
                'plan_id' => $plan->id,
                'exercise_id' => $item['exercise_id'] ?? null,
                'exercise_name' => $item['exercise_name'],
                'day_of_week' => $item['day_of_week'] ?? null,
                'sets' => $item['sets'],
                'reps' => $item['reps'],
                'weight' => $item['weight'] ?? null,
                'rest_time' => $item['rest_time'] ?? '60s',
                'notes' => $item['notes'] ?? null,
                'order_index' => $item['order_index'] ?? $index,
            ]);
        }
    }

    /**
     * 同步饮食计划（删除旧的，插入新的）
     */
    private function syncNutrition(TrainingPlan $plan, array $items): void
    {
        $plan->nutritionPlans()->delete();

        foreach ($items as $index => $item) {
            UserNutritionPlan::create([
                'plan_id' => $plan->id,
                'food_id' => $item['food_id'] ?? null,
                'food_name' => $item['food_name'],
                'meal_type' => $item['meal_type'],
                'portion_grams' => $item['portion_grams'] ?? 100,
                'day_of_week' => $item['day_of_week'] ?? null,
                'notes' => $item['notes'] ?? null,
                'order_index' => $item['order_index'] ?? $index,
            ]);
        }
    }
}
