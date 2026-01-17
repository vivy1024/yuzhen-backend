<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\TrainingPlan;
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
            
            $plans = $query->orderBy('created_at', 'desc')->get();
            
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
                    'exerciseCount' => is_array($plan->exercises) ? count($plan->exercises) : 0,
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
                ->with('chatSession')
                ->findOrFail($id);
            
            return $this->success([
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'weeks' => $plan->duration_weeks,
                'frequency' => $plan->workouts_per_week,
                'exercises' => $plan->exercises,
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
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:100',
                'description' => 'nullable|string',
                'is_active' => 'sometimes|boolean',
                'started_at' => 'nullable|date',
                'completed_at' => 'nullable|date',
            ]);
            
            $user = $request->user();
            
            $plan = TrainingPlan::where('user_id', $user->id)
                ->findOrFail($id);
            
            $plan->update($validated);
            
            Log::info('更新训练计划成功', [
                'plan_id' => $plan->id,
                'user_id' => $user->id,
            ]);
            
            return $this->success([
                'id' => $plan->id,
                'name' => $plan->name,
                'updatedAt' => $plan->updated_at->toIso8601String(),
            ], '更新成功');
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
}
