<?php

namespace App\Modules\Training\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Training\Services\TrainingPlanService;
use App\Modules\Training\Resources\TrainingPlanResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Training Plan Controller
 * 
 * 训练计划API控制器
 */
class TrainingPlanController extends BaseController
{
    protected TrainingPlanService $planService;
    
    public function __construct(TrainingPlanService $planService)
    {
        $this->planService = $planService;
    }

    /**
     * 获取用户的训练计划列表
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // 使用request()->user()获取通过JWT中间件设置的用户
            $user = $request->user();
            $userId = $user ? $user->id : null;

            if (!$userId) {
                return $this->error('用户未认证', 401);
            }

            $filters = [
                'is_active' => $request->input('is_active'),
                'goal' => $request->input('goal'),
            ];

            $plans = $this->planService->getUserPlans($userId, $filters);
            
            // 直接返回数组，因为Service层已经返回数组格式
            return $this->success(
                $plans,
                '获取训练计划列表成功'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取训练计划列表');
        }
    }

    /**
     * 获取训练计划详情
     */
    public function show(int $id): JsonResponse
    {
        try {
            $plan = $this->planService->getDetail($id);
            
            if (!$plan) {
                return $this->fail('训练计划不存在', 404);
            }
            
            // 直接返回数组，因为Service层已经返回数组格式
            return $this->success(
                $plan,
                '获取训练计划详情成功'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取训练计划详情');
        }
    }

    /**
     * 创建训练计划
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $data = $request->all();

            $plan = $this->planService->create($userId, $data);

            return $this->success($plan, '创建训练计划成功', 201);

        } catch (\Exception $e) {
            return $this->handleException($e, '创建训练计划');
        }
    }

    /**
     * 从AI对话创建训练计划
     *
     * POST /api/training/plans/ai-import
     */
    public function aiImport(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'goal' => 'nullable|string|in:hypertrophy,fat_loss,strength,endurance,body_shaping,general_fitness,functional,rehabilitation,lose_weight,gain_muscle,maintain,improve_fitness', // lose_weight,gain_muscle,maintain,improve_fitness deprecated, 保留兼容
                'frequency' => 'nullable|integer|min:1|max:7',
                'duration' => 'nullable|integer|min:1|max:52',
                'difficulty_level' => 'nullable|string|in:novice,beginner,intermediate,advanced',
                'source' => 'nullable|string|in:manual,ai_generated',
                'exercises' => 'nullable|array',
                'exercises.*.exercise_id' => 'nullable|integer',
                'exercises.*.exercise_name' => 'required|string|max:255',
                'exercises.*.sets' => 'nullable|integer|min:1|max:10',
                'exercises.*.reps' => 'nullable|string|max:50',
                'exercises.*.weight' => 'nullable|string|max:50',
                'exercises.*.rest_time' => 'nullable|string|max:20',
                'exercises.*.notes' => 'nullable|string|max:500',
                'exercises.*.order_index' => 'nullable|integer|min:0',
            ]);

            $plan = $this->planService->createFromAI($userId, $data);

            return $this->success($plan, 'AI训练计划导入成功', 201);

        } catch (\Exception $e) {
            return $this->handleException($e, 'AI训练计划导入');
        }
    }

    /**
     * 更新训练计划
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $data = $request->all();
            $plan = $this->planService->update($id, $data);
            
            if (!$plan) {
                return $this->fail('更新训练计划失败', 500);
            }
            
            // 直接返回数组
            return $this->success(
                $plan,
                '更新训练计划成功'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '更新训练计划');
        }
    }

    /**
     * 删除训练计划（软删除）
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->planService->delete($id);
            
            if (!$result) {
                return $this->fail('删除训练计划失败', 500);
            }
            
            return $this->success(null, '删除训练计划成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '删除训练计划');
        }
    }

    /**
     * 激活训练计划
     */
    public function activate(int $id): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            // 停用用户的其他计划
            $this->planService->deactivateUserPlans($userId);
            
            // 激活当前计划
            $plan = $this->planService->activate($id);
            
            if (!$plan) {
                return $this->fail('激活训练计划失败', 500);
            }
            
            // 直接返回数组
            return $this->success(
                $plan,
                '训练计划已激活'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '激活训练计划');
        }
    }

    /**
     * 完成训练计划
     */
    public function complete(int $id): JsonResponse
    {
        try {
            $result = $this->planService->complete($id);
            
            if (!$result) {
                return $this->fail('完成训练计划失败');
            }
            
            return $this->success(null, '训练计划已完成');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '完成训练计划');
        }
    }

    /**
     * 获取训练计划进度统计
     * 
     * GET /api/training-plans/{id}/progress-stats
     * 
     * Requirements: 5.2, 5.3 - 训练完成进度更新和历史完成率
     */
    public function progressStats(int $id): JsonResponse
    {
        try {
            $stats = $this->planService->getPlanProgressStats($id);
            
            return $this->success($stats, '获取计划进度统计成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取计划进度统计');
        }
    }

    /**
     * 获取训练计划关联的训练日志
     * 
     * GET /api/training-plans/{id}/training-logs
     * 
     * Requirements: 5.1 - 计划与训练记录关联
     */
    public function trainingLogs(int $id): JsonResponse
    {
        try {
            $logs = $this->planService->getPlanTrainingLogs($id);
            
            return $this->success($logs, '获取计划训练日志成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取计划训练日志');
        }
    }
}

