<?php

namespace App\Modules\Training\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\TrainingLog;
use App\Models\PersonalBest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Internal Training Controller - 内部训练API控制器
 * 
 * 为DAML-RAG服务提供训练日志和个人最佳记录的内部API
 * 
 * @version 1.0.0
 * @date 2025-12-26
 * 
 * Requirements: 数据访问 - 为DAML-RAG提供训练数据
 */
class InternalTrainingController extends BaseController
{
    // ============= 训练日志API =============

    /**
     * 获取用户训练日志列表
     * 
     * GET /api/internal/training-logs/{userId}
     */
    public function getTrainingLogs(Request $request, int $userId): JsonResponse
    {
        try {
            $query = TrainingLog::forUser($userId)
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
            $perPage = $request->input('per_page', 100);
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
     * 获取用户训练统计
     * 
     * GET /api/internal/training-logs/{userId}/stats
     */
    public function getTrainingStats(Request $request, int $userId): JsonResponse
    {
        try {
            $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
            $endDate = $request->input('end_date', now()->toDateString());

            $logs = TrainingLog::forUser($userId)
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

    // ============= 个人最佳记录API =============

    /**
     * 获取用户所有个人最佳记录
     * 
     * GET /api/internal/personal-bests/{userId}
     */
    public function getPersonalBests(Request $request, int $userId): JsonResponse
    {
        try {
            $query = PersonalBest::forUser($userId)
                ->orderBy('updated_at', 'desc');

            // 分页
            $perPage = $request->input('per_page', 100);
            $records = $query->paginate($perPage);

            return $this->success([
                'rows' => $records->items(),
                'total' => $records->total(),
                'page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total_pages' => $records->lastPage(),
            ], '获取个人最佳记录列表成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取个人最佳记录列表');
        }
    }

    /**
     * 获取特定动作的个人最佳记录
     * 
     * GET /api/internal/personal-bests/{userId}/{exerciseId}
     */
    public function getPersonalBest(int $userId, string $exerciseId): JsonResponse
    {
        try {
            $record = PersonalBest::forUser($userId)
                ->forExercise($exerciseId)
                ->first();

            if (!$record) {
                return $this->fail('未找到该动作的个人最佳记录', 404);
            }

            return $this->success($record, '获取个人最佳记录成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取个人最佳记录');
        }
    }

    /**
     * 更新个人最佳记录
     * 
     * POST /api/internal/personal-bests/{userId}/update
     */
    public function updatePersonalBest(Request $request, int $userId): JsonResponse
    {
        try {
            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'exercise_id' => 'required|string|max:50',
                'exercise_name' => 'nullable|string|max:255',
                'weight' => 'required|numeric|min:0.1|max:1000',
                'reps' => 'required|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败: ' . $validator->errors()->first(), 422);
            }

            $data = $validator->validated();

            // 获取或创建个人最佳记录
            $pb = PersonalBest::getOrCreate(
                $userId,
                $data['exercise_id'],
                $data['exercise_name'] ?? null
            );

            // 记录更新前的数据
            $previousBest = [
                'best_weight' => $pb->best_weight,
                'best_reps' => $pb->best_reps,
                'estimated_1rm' => $pb->estimated_1rm,
            ];

            // 尝试更新最佳记录
            $isNewRecord = $pb->updateBest($data['weight'], $data['reps']);

            // 计算新的估算1RM
            $new1RM = PersonalBest::calculate1RM($data['weight'], $data['reps']);

            return $this->success([
                'personal_best' => $pb->fresh(),
                'is_new_record' => $isNewRecord,
                'previous_best' => $previousBest,
                'current_attempt' => [
                    'weight' => $data['weight'],
                    'reps' => $data['reps'],
                    'estimated_1rm' => $new1RM,
                ],
            ], $isNewRecord ? '恭喜！打破个人最佳记录！' : '记录已更新');

        } catch (\Exception $e) {
            return $this->handleException($e, '更新个人最佳记录');
        }
    }

    /**
     * 获取用户力量排行榜
     * 
     * GET /api/internal/personal-bests/{userId}/leaderboard
     */
    public function getLeaderboard(Request $request, int $userId): JsonResponse
    {
        try {
            $limit = $request->input('limit', 20);

            $records = PersonalBest::forUser($userId)
                ->whereNotNull('estimated_1rm')
                ->where('estimated_1rm', '>', 0)
                ->orderBy('estimated_1rm', 'desc')
                ->limit($limit)
                ->get();

            return $this->success([
                'leaderboard' => $records,
                'total' => $records->count(),
            ], '获取力量排行榜成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取力量排行榜');
        }
    }
}
