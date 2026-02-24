<?php

namespace App\Modules\Training\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\TrainingLog;
use App\Models\PersonalBest;
use App\Modules\User\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * Training Log Controller - 训练日志控制器
 * 
 * 实现闭环学习系统的训练日志CRUD操作
 * 
 * @version 1.0.0
 * @date 2025-12-26
 * 
 * Requirements: 6.1, 6.2, 6.3
 */
class TrainingLogController extends BaseController
{
    /**
     * 获取用户的训练日志列表
     * 
     * GET /api/training-logs
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            $query = TrainingLog::forUser($user->id)
                ->orderBy('session_date', 'desc');

            // 日期范围筛选
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->dateRange($request->input('start_date'), $request->input('end_date'));
            }

            // 中周期筛选
            if ($request->has('mesocycle_id')) {
                $query->forMesocycle($request->input('mesocycle_id'));
            }

            // 分页
            $perPage = $request->input('per_page', 20);
            $logs = $query->paginate($perPage);

            return $this->success([
                'rows' => $logs->items(),
                'total' => $logs->total(),
                'page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total_pages' => $logs->lastPage(),
            ], '获取训练日志列表成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取训练日志列表');
        }
    }

    /**
     * 获取训练日志详情
     * 
     * GET /api/training-logs/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            $log = TrainingLog::forUser($user->id)->find($id);

            if (!$log) {
                return $this->fail('训练日志不存在', 404);
            }

            return $this->success($log, '获取训练日志详情成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取训练日志详情');
        }
    }

    /**
     * 记录训练会话
     * 
     * POST /api/training-logs/session
     * 
     * Requirements: 6.1 - 记录训练日期、动作、组数、次数、重量
     * Requirements: 5.1, 5.2 - 关联训练计划并更新进度
     */
    public function recordSession(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'session_date' => 'required|date',
                'training_plan_id' => 'nullable|integer|exists:training_plans,id',
                'plan_week' => 'nullable|integer|min:1|max:52',
                'plan_day' => 'nullable|integer|min:1|max:7',
                'planned_exercises' => 'required|array|min:1',
                'planned_exercises.*.exercise_id' => 'required|string',
                'planned_exercises.*.exercise_name' => 'required|string|max:255',
                'planned_exercises.*.sets' => 'required|integer|min:1|max:20',
                'planned_exercises.*.reps' => 'required|integer|min:1|max:100',
                'planned_exercises.*.weight' => 'nullable|numeric|min:0|max:1000',
                'actual_exercises' => 'nullable|array',
                'actual_exercises.*.exercise_id' => 'required|string',
                'actual_exercises.*.completed_sets' => 'required|integer|min:0|max:20',
                'actual_exercises.*.rpe' => 'nullable|numeric|min:1|max:10',
                'actual_exercises.*.weight' => 'nullable|numeric|min:0|max:1000',
                'actual_exercises.*.reps_per_set' => 'nullable|array',
                'week_number' => 'nullable|integer|min:1|max:52',
                'mesocycle_id' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:1000',
            ], [
                'planned_exercises.required' => '计划动作列表不能为空',
                'planned_exercises.*.exercise_id.required' => '动作ID不能为空',
                'planned_exercises.*.sets.min' => '组数至少为1',
                'actual_exercises.*.rpe.min' => 'RPE值必须在1-10范围内',
                'actual_exercises.*.rpe.max' => 'RPE值必须在1-10范围内',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败: ' . $validator->errors()->first(), 422);
            }

            $data = $validator->validated();

            // 验证actual_exercises中的RPE值
            if (!empty($data['actual_exercises'])) {
                foreach ($data['actual_exercises'] as $exercise) {
                    if (isset($exercise['rpe']) && ($exercise['rpe'] < 1 || $exercise['rpe'] > 10)) {
                        return $this->fail('RPE值必须在1-10范围内', 422);
                    }
                }
            }

            DB::beginTransaction();
            try {
                // 创建训练日志
                $log = new TrainingLog();
                $log->user_id = $user->id;
                $log->training_plan_id = $data['training_plan_id'] ?? null;
                $log->plan_week = $data['plan_week'] ?? null;
                $log->plan_day = $data['plan_day'] ?? null;
                $log->session_date = $data['session_date'];
                $log->planned_exercises = $data['planned_exercises'];
                $log->actual_exercises = $data['actual_exercises'] ?? [];
                $log->week_number = $data['week_number'] ?? null;
                $log->mesocycle_id = $data['mesocycle_id'] ?? null;
                $log->notes = $data['notes'] ?? null;

                // 计算完成率和平均RPE
                $log->completion_rate = $log->calculateCompletionRate();
                $log->avg_rpe = $log->calculateAvgRpe();

                $log->save();

                // 更新个人最佳记录
                if (!empty($data['actual_exercises'])) {
                    $this->updatePersonalBests($user->id, $data['actual_exercises']);
                }

                // 更新训练计划进度（Requirements: 5.2）
                if ($log->training_plan_id) {
                    $this->updatePlanProgress($log->training_plan_id);
                }

                // 更新连续训练天数
                $this->updateTrainingStreak($user->id, $data['session_date']);

                DB::commit();

                return $this->success($log, '训练会话记录成功', 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->handleException($e, '记录训练会话');
        }
    }

    /**
     * 记录单组训练
     * 
     * POST /api/training-logs/{id}/set
     * 
     * Requirements: 6.2 - 允许用户输入RPE评分(1-10)
     */
    public function recordSet(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            $log = TrainingLog::forUser($user->id)->find($id);
            if (!$log) {
                return $this->fail('训练日志不存在', 404);
            }

            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'exercise_id' => 'required|string',
                'set_number' => 'required|integer|min:1|max:20',
                'reps' => 'required|integer|min:1|max:100',
                'weight' => 'nullable|numeric|min:0|max:1000',
                'rpe' => 'nullable|numeric|min:1|max:10',
                'notes' => 'nullable|string|max:500',
            ], [
                'rpe.min' => 'RPE值必须在1-10范围内',
                'rpe.max' => 'RPE值必须在1-10范围内',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败: ' . $validator->errors()->first(), 422);
            }

            $data = $validator->validated();

            // 更新actual_exercises
            $actualExercises = $log->actual_exercises ?? [];
            $exerciseIndex = null;

            // 查找对应的动作
            foreach ($actualExercises as $index => $exercise) {
                if ($exercise['exercise_id'] === $data['exercise_id']) {
                    $exerciseIndex = $index;
                    break;
                }
            }

            // 如果动作不存在，创建新的
            if ($exerciseIndex === null) {
                $actualExercises[] = [
                    'exercise_id' => $data['exercise_id'],
                    'completed_sets' => 0,
                    'rpe' => null,
                    'sets_detail' => [],
                ];
                $exerciseIndex = count($actualExercises) - 1;
            }

            // 添加组数据
            $setData = [
                'set_number' => $data['set_number'],
                'reps' => $data['reps'],
                'weight' => $data['weight'] ?? null,
                'rpe' => $data['rpe'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            if (!isset($actualExercises[$exerciseIndex]['sets_detail'])) {
                $actualExercises[$exerciseIndex]['sets_detail'] = [];
            }
            $actualExercises[$exerciseIndex]['sets_detail'][] = $setData;
            $actualExercises[$exerciseIndex]['completed_sets'] = count($actualExercises[$exerciseIndex]['sets_detail']);

            // 计算该动作的平均RPE
            $rpeValues = array_filter(array_column($actualExercises[$exerciseIndex]['sets_detail'], 'rpe'));
            if (!empty($rpeValues)) {
                $actualExercises[$exerciseIndex]['rpe'] = round(array_sum($rpeValues) / count($rpeValues), 1);
            }

            // 更新日志
            $log->actual_exercises = $actualExercises;
            $log->completion_rate = $log->calculateCompletionRate();
            $log->avg_rpe = $log->calculateAvgRpe();
            $log->save();

            // 更新个人最佳记录
            if (isset($data['weight']) && $data['weight'] > 0) {
                $this->updatePersonalBest($user->id, $data['exercise_id'], $data['weight'], $data['reps']);
            }

            return $this->success($log, '训练组记录成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '记录训练组');
        }
    }


    /**
     * 更新训练日志
     * 
     * PUT /api/training-logs/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            $log = TrainingLog::forUser($user->id)->find($id);
            if (!$log) {
                return $this->fail('训练日志不存在', 404);
            }

            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'session_date' => 'sometimes|date',
                'planned_exercises' => 'sometimes|array|min:1',
                'planned_exercises.*.exercise_id' => 'required|string',
                'planned_exercises.*.exercise_name' => 'required|string|max:255',
                'planned_exercises.*.sets' => 'required|integer|min:1|max:20',
                'planned_exercises.*.reps' => 'required|integer|min:1|max:100',
                'planned_exercises.*.weight' => 'nullable|numeric|min:0|max:1000',
                'actual_exercises' => 'sometimes|array',
                'actual_exercises.*.exercise_id' => 'required|string',
                'actual_exercises.*.completed_sets' => 'required|integer|min:0|max:20',
                'actual_exercises.*.rpe' => 'nullable|numeric|min:1|max:10',
                'actual_exercises.*.weight' => 'nullable|numeric|min:0|max:1000',
                'week_number' => 'nullable|integer|min:1|max:52',
                'mesocycle_id' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:1000',
            ], [
                'actual_exercises.*.rpe.min' => 'RPE值必须在1-10范围内',
                'actual_exercises.*.rpe.max' => 'RPE值必须在1-10范围内',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败: ' . $validator->errors()->first(), 422);
            }

            $data = $validator->validated();

            // 验证actual_exercises中的RPE值
            if (!empty($data['actual_exercises'])) {
                foreach ($data['actual_exercises'] as $exercise) {
                    if (isset($exercise['rpe']) && ($exercise['rpe'] < 1 || $exercise['rpe'] > 10)) {
                        return $this->fail('RPE值必须在1-10范围内', 422);
                    }
                }
            }

            // 更新字段
            $log->fill($data);

            // 重新计算完成率和平均RPE
            $log->completion_rate = $log->calculateCompletionRate();
            $log->avg_rpe = $log->calculateAvgRpe();

            $log->save();

            return $this->success($log, '训练日志更新成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '更新训练日志');
        }
    }

    /**
     * 删除训练日志
     * 
     * DELETE /api/training-logs/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            $log = TrainingLog::forUser($user->id)->find($id);
            if (!$log) {
                return $this->fail('训练日志不存在', 404);
            }

            $log->delete();

            return $this->success(null, '训练日志删除成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '删除训练日志');
        }
    }

    /**
     * 获取用户训练统计
     * 
     * GET /api/training-logs/stats
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
            $endDate = $request->input('end_date', now()->toDateString());

            $logs = TrainingLog::forUser($user->id)
                ->dateRange($startDate, $endDate)
                ->get();

            $stats = [
                'total_sessions' => $logs->count(),
                'avg_completion_rate' => $logs->avg('completion_rate') ?? 0,
                'avg_rpe' => $logs->avg('avg_rpe') ?? 0,
                'total_exercises' => 0,
                'total_sets' => 0,
            ];

            foreach ($logs as $log) {
                if (!empty($log->actual_exercises)) {
                    $stats['total_exercises'] += count($log->actual_exercises);
                    foreach ($log->actual_exercises as $exercise) {
                        $stats['total_sets'] += $exercise['completed_sets'] ?? 0;
                    }
                }
            }

            return $this->success($stats, '获取训练统计成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取训练统计');
        }
    }

    /**
     * 完成训练会话
     *
     * POST /api/training-logs/{id}/complete
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            $log = TrainingLog::forUser($user->id)->find($id);
            if (!$log) {
                return $this->fail('训练日志不存在', 404);
            }

            $log->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // 更新训练计划进度
            if ($log->training_plan_id) {
                $this->updatePlanProgress($log->training_plan_id);
            }

            return $this->success($log->fresh(), '训练完成');
        } catch (\Exception $e) {
            return $this->handleException($e, '完成训练会话');
        }
    }

    /**
     * 从训练计划创建训练会话
     *
     * POST /api/training-logs/from-plan
     */
    public function createFromPlan(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            $validator = Validator::make($request->all(), [
                'plan_id' => 'required|integer|exists:training_plans,id',
                'date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败: ' . $validator->errors()->first(), 422);
            }

            $data = $validator->validated();
            $plan = \App\Modules\Training\Models\TrainingPlan::where('user_id', $user->id)
                ->findOrFail($data['plan_id']);

            // 获取计划的动作列表
            $exercises = [];
            if (is_array($plan->exercises) && !empty($plan->exercises)) {
                $exercises = $plan->exercises;
            }

            $log = new TrainingLog();
            $log->user_id = $user->id;
            $log->training_plan_id = $plan->id;
            $log->session_date = $data['date'] ?? now()->toDateString();
            $log->planned_exercises = $exercises;
            $log->actual_exercises = [];
            $log->status = 'in_progress';
            $log->completion_rate = 0;
            $log->avg_rpe = 0;
            $log->save();

            return $this->success($log, '训练会话已创建', 201);
        } catch (\Exception $e) {
            return $this->handleException($e, '从计划创建训练会话');
        }
    }

    /**
     * 更新个人最佳记录（批量）
     */
    private function updatePersonalBests(int $userId, array $exercises): void
    {
        foreach ($exercises as $exercise) {
            if (isset($exercise['weight']) && $exercise['weight'] > 0) {
                $reps = $exercise['reps_per_set'][0] ?? $exercise['reps'] ?? 1;
                $this->updatePersonalBest(
                    $userId,
                    $exercise['exercise_id'],
                    $exercise['weight'],
                    $reps,
                    $exercise['exercise_name'] ?? null
                );
            }
        }
    }

    /**
     * 更新单个动作的个人最佳记录
     */
    private function updatePersonalBest(int $userId, string $exerciseId, float $weight, int $reps, ?string $exerciseName = null): void
    {
        $pb = PersonalBest::getOrCreate($userId, $exerciseId, $exerciseName);
        $pb->updateBest($weight, $reps);
    }

    /**
     * 更新训练计划进度
     * 
     * Requirements: 5.2 - 完成训练后更新计划进度
     * 
     * @param int $planId 训练计划ID
     */
    private function updatePlanProgress(int $planId): void
    {
        try {
            $plan = \App\Modules\Training\Models\TrainingPlan::find($planId);
            
            if (!$plan) {
                return;
            }

            // 获取关联的训练日志数量
            $completedSessions = TrainingLog::where('training_plan_id', $planId)->count();
            
            // 计算完成率
            $totalSessions = $plan->total_sessions ?? 0;
            $completionRate = $totalSessions > 0 
                ? round(($completedSessions / $totalSessions) * 100, 1) 
                : 0;

            // 获取最新的训练日志来确定当前周和天
            $latestLog = TrainingLog::where('training_plan_id', $planId)
                ->orderBy('session_date', 'desc')
                ->first();

            // 更新计划统计信息
            $stats = $plan->stats ?? [];
            $stats['completed_sessions'] = $completedSessions;
            $stats['completion_rate'] = $completionRate;
            
            if ($latestLog) {
                $stats['current_week_index'] = $latestLog->plan_week ?? ($stats['current_week_index'] ?? 0);
                $stats['current_day_index'] = $latestLog->plan_day ?? ($stats['current_day_index'] ?? 0);
            }

            $plan->stats = $stats;
            $plan->save();

            // 如果完成率达到100%，标记计划为已完成
            if ($completionRate >= 100) {
                $plan->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            \Log::info('训练计划进度已更新', [
                'plan_id' => $planId,
                'completed_sessions' => $completedSessions,
                'completion_rate' => $completionRate,
            ]);

        } catch (\Exception $e) {
            \Log::error('更新训练计划进度失败', [
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 更新用户连续训练天数
     */
    private function updateTrainingStreak(int $userId, string $sessionDate): void
    {
        try {
            $profile = UserProfile::where('user_id', $userId)->first();
            if ($profile) {
                $profile->updateTrainingStreak($sessionDate);
            }
        } catch (\Exception $e) {
            \Log::error('更新训练连续天数失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
