<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\User\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * 训练记录控制器
 * 
 * 用于记录用户的训练数据和力量进步
 * 
 * @version 1.1.0
 * @date 2026-01-17
 */
class TrainingRecordController extends BaseController
{
    /**
     * 记录训练数据
     * 
     * POST /api/training/record
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function recordTraining(Request $request): JsonResponse
    {
        try {
            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'exercise_name' => 'required|string|max:100',
                'weight' => 'required|numeric|min:0',
                'reps' => 'required|integer|min:1|max:100',
                'date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->fail('数据验证失败', 422, ['errors' => $validator->errors()]);
            }

            $userId = $request->user()->id;

            // 获取用户档案
            $userProfile = UserProfile::where('user_id', $userId)->firstOrFail();

            // 记录训练数据
            $progressData = $userProfile->recordStrengthProgress(
                $request->exercise_name,
                $request->weight,
                $request->reps,
                $request->date
            );

            return $this->success([
                'exercise_name' => $request->exercise_name,
                'progress' => $progressData,
                'overall_strength_level' => $userProfile->getOverallStrengthLevel(),
            ], '训练数据记录成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '记录训练数据');
        }
    }

    /**
     * 获取力量进步曲线
     * 
     * GET /api/training/progress/{user_id}
     * GET /api/training/progress/{user_id}/{exercise_name}
     * 
     * @param int $userId
     * @param string|null $exerciseName
     * @return JsonResponse
     */
    public function getStrengthProgress(Request $request, ?string $exerciseName = null): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            // 获取用户档案
            $userProfile = UserProfile::where('user_id', $userId)->firstOrFail();

            if ($exerciseName) {
                // 获取特定动作的进步数据
                $progress = $userProfile->getStrengthProgress($exerciseName);

                if (!$progress) {
                    return $this->fail("未找到动作 {$exerciseName} 的训练记录", 404);
                }

                return $this->success([
                    'exercise_name' => $exerciseName,
                    'progress' => $progress,
                ], '获取成功');
            } else {
                // 获取所有动作的进步数据
                $strengthProgress = $userProfile->strength_progress ?? [];
                $current1RMs = $userProfile->getAllCurrent1RMs();
                $overallLevel = $userProfile->getOverallStrengthLevel();

                return $this->success([
                    'strength_progress' => $strengthProgress,
                    'current_1rms' => $current1RMs,
                    'overall_strength_level' => $overallLevel,
                ], '获取成功');
            }

        } catch (\Exception $e) {
            return $this->handleException($e, '获取力量进步数据');
        }
    }

    /**
     * 批量记录训练数据
     * 
     * POST /api/training/record-batch
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function recordTrainingBatch(Request $request): JsonResponse
    {
        try {
            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'records' => 'required|array|min:1',
                'records.*.exercise_name' => 'required|string|max:100',
                'records.*.weight' => 'required|numeric|min:0',
                'records.*.reps' => 'required|integer|min:1|max:100',
                'records.*.date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->fail('数据验证失败', 422, ['errors' => $validator->errors()]);
            }

            $userId = $request->user()->id;

            // 获取用户档案
            $userProfile = UserProfile::where('user_id', $userId)->firstOrFail();

            $results = [];

            // 批量记录训练数据
            foreach ($request->records as $record) {
                $progressData = $userProfile->recordStrengthProgress(
                    $record['exercise_name'],
                    $record['weight'],
                    $record['reps'],
                    $record['date'] ?? null
                );

                $results[] = [
                    'exercise_name' => $record['exercise_name'],
                    'progress' => $progressData,
                ];
            }

            return $this->success([
                'records' => $results,
                'overall_strength_level' => $userProfile->getOverallStrengthLevel(),
            ], '批量记录训练数据成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '批量记录训练数据');
        }
    }

    /**
     * 删除训练记录
     * 
     * DELETE /api/training/record/{user_id}/{exercise_name}/{index}
     * 
     * @param int $userId
     * @param string $exerciseName
     * @param int $index
     * @return JsonResponse
     */
    public function deleteTrainingRecord(Request $request, string $exerciseName, int $index): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            // 获取用户档案
            $userProfile = UserProfile::where('user_id', $userId)->firstOrFail();

            $strengthProgress = $userProfile->strength_progress ?? [];

            if (!isset($strengthProgress[$exerciseName])) {
                return $this->fail("未找到动作 {$exerciseName} 的训练记录", 404);
            }

            if (!isset($strengthProgress[$exerciseName]['history'][$index])) {
                return $this->fail("未找到索引 {$index} 的训练记录", 404);
            }

            // 删除指定记录
            array_splice($strengthProgress[$exerciseName]['history'], $index, 1);

            // 如果历史记录为空，删除整个动作数据
            if (empty($strengthProgress[$exerciseName]['history'])) {
                unset($strengthProgress[$exerciseName]);
            } else {
                // 重新计算当前1RM（取历史最大值）
                $maxEstimated1RM = max(array_column($strengthProgress[$exerciseName]['history'], 'estimated_1rm'));
                $strengthProgress[$exerciseName]['current_1rm'] = $maxEstimated1RM;
                $strengthProgress[$exerciseName]['last_updated'] = now()->toISOString();
            }

            // 保存更新
            $userProfile->strength_progress = $strengthProgress;
            $userProfile->save();

            return $this->success([
                'strength_progress' => $strengthProgress,
            ], '训练记录删除成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '删除训练记录');
        }
    }
}
