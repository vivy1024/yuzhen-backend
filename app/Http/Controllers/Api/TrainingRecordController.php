<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * 训练记录控制器
 * 
 * 用于记录用户的训练数据和力量进步
 * 
 * @version 1.0.0
 * @date 2025-12-19
 */
class TrainingRecordController extends Controller
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
        // 验证请求数据
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'exercise_name' => 'required|string|max:100',
            'weight' => 'required|numeric|min:0',
            'reps' => 'required|integer|min:1|max:100',
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '数据验证失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // 获取用户档案
            $userProfile = UserProfile::where('user_id', $request->user_id)->firstOrFail();

            // 记录训练数据
            $progressData = $userProfile->recordStrengthProgress(
                $request->exercise_name,
                $request->weight,
                $request->reps,
                $request->date
            );

            return response()->json([
                'success' => true,
                'message' => '训练数据记录成功',
                'data' => [
                    'exercise_name' => $request->exercise_name,
                    'progress' => $progressData,
                    'overall_strength_level' => $userProfile->getOverallStrengthLevel(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '记录训练数据失败：' . $e->getMessage(),
            ], 500);
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
    public function getStrengthProgress(int $userId, ?string $exerciseName = null): JsonResponse
    {
        try {
            // 获取用户档案
            $userProfile = UserProfile::where('user_id', $userId)->firstOrFail();

            if ($exerciseName) {
                // 获取特定动作的进步数据
                $progress = $userProfile->getStrengthProgress($exerciseName);

                if (!$progress) {
                    return response()->json([
                        'success' => false,
                        'message' => "未找到动作 {$exerciseName} 的训练记录",
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'exercise_name' => $exerciseName,
                        'progress' => $progress,
                    ],
                ]);
            } else {
                // 获取所有动作的进步数据
                $strengthProgress = $userProfile->strength_progress ?? [];
                $current1RMs = $userProfile->getAllCurrent1RMs();
                $overallLevel = $userProfile->getOverallStrengthLevel();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'strength_progress' => $strengthProgress,
                        'current_1rms' => $current1RMs,
                        'overall_strength_level' => $overallLevel,
                    ],
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取力量进步数据失败：' . $e->getMessage(),
            ], 500);
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
        // 验证请求数据
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'records' => 'required|array|min:1',
            'records.*.exercise_name' => 'required|string|max:100',
            'records.*.weight' => 'required|numeric|min:0',
            'records.*.reps' => 'required|integer|min:1|max:100',
            'records.*.date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '数据验证失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // 获取用户档案
            $userProfile = UserProfile::where('user_id', $request->user_id)->firstOrFail();

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

            return response()->json([
                'success' => true,
                'message' => '批量记录训练数据成功',
                'data' => [
                    'records' => $results,
                    'overall_strength_level' => $userProfile->getOverallStrengthLevel(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '批量记录训练数据失败：' . $e->getMessage(),
            ], 500);
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
    public function deleteTrainingRecord(int $userId, string $exerciseName, int $index): JsonResponse
    {
        try {
            // 获取用户档案
            $userProfile = UserProfile::where('user_id', $userId)->firstOrFail();

            $strengthProgress = $userProfile->strength_progress ?? [];

            if (!isset($strengthProgress[$exerciseName])) {
                return response()->json([
                    'success' => false,
                    'message' => "未找到动作 {$exerciseName} 的训练记录",
                ], 404);
            }

            if (!isset($strengthProgress[$exerciseName]['history'][$index])) {
                return response()->json([
                    'success' => false,
                    'message' => "未找到索引 {$index} 的训练记录",
                ], 404);
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

            return response()->json([
                'success' => true,
                'message' => '训练记录删除成功',
                'data' => [
                    'strength_progress' => $strengthProgress,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '删除训练记录失败：' . $e->getMessage(),
            ], 500);
        }
    }
}
