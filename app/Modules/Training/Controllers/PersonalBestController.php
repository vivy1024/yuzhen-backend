<?php

namespace App\Modules\Training\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\PersonalBest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Personal Best Controller - 个人最佳记录控制器
 * 
 * 实现个人最佳记录的CRUD和自动更新逻辑
 * 
 * @version 1.0.0
 * @date 2025-12-26
 * 
 * Requirements: 6.4 - 自动更新个人最佳记录(PersonalBest)
 */
class PersonalBestController extends BaseController
{
    /**
     * 获取用户的所有个人最佳记录
     * 
     * GET /api/personal-bests
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            $query = PersonalBest::forUser($user->id)
                ->orderBy('updated_at', 'desc');

            // 分页
            $perPage = $request->input('per_page', 50);
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
     * GET /api/personal-bests/{exerciseId}
     * 
     * Requirements: 6.4 - 实现get_personal_best API端点
     */
    public function show(Request $request, string $exerciseId): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            $record = PersonalBest::forUser($user->id)
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
     * 更新个人最佳记录（自动判断是否打破记录）
     * 
     * POST /api/personal-bests/update
     * 
     * Requirements: 6.4 - 实现update_personal_best逻辑（自动更新）
     */
    public function updatePersonalBest(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'exercise_id' => 'required|string|max:50',
                'exercise_name' => 'nullable|string|max:255',
                'weight' => 'required|numeric|min:0.1|max:1000',
                'reps' => 'required|integer|min:1|max:100',
            ], [
                'exercise_id.required' => '动作ID不能为空',
                'weight.required' => '重量不能为空',
                'weight.min' => '重量必须大于0',
                'reps.required' => '次数不能为空',
                'reps.min' => '次数至少为1',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败: ' . $validator->errors()->first(), 422);
            }

            $data = $validator->validated();

            // 获取或创建个人最佳记录
            $pb = PersonalBest::getOrCreate(
                $user->id,
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
     * 批量更新个人最佳记录
     * 
     * POST /api/personal-bests/batch-update
     */
    public function batchUpdate(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'records' => 'required|array|min:1|max:50',
                'records.*.exercise_id' => 'required|string|max:50',
                'records.*.exercise_name' => 'nullable|string|max:255',
                'records.*.weight' => 'required|numeric|min:0.1|max:1000',
                'records.*.reps' => 'required|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败: ' . $validator->errors()->first(), 422);
            }

            $results = [];
            $newRecordsCount = 0;

            foreach ($request->input('records') as $record) {
                $pb = PersonalBest::getOrCreate(
                    $user->id,
                    $record['exercise_id'],
                    $record['exercise_name'] ?? null
                );

                $isNewRecord = $pb->updateBest($record['weight'], $record['reps']);
                
                if ($isNewRecord) {
                    $newRecordsCount++;
                }

                $results[] = [
                    'exercise_id' => $record['exercise_id'],
                    'is_new_record' => $isNewRecord,
                    'current_best' => [
                        'weight' => $pb->best_weight,
                        'reps' => $pb->best_reps,
                        'estimated_1rm' => $pb->estimated_1rm,
                    ],
                ];
            }

            return $this->success([
                'results' => $results,
                'total_processed' => count($results),
                'new_records_count' => $newRecordsCount,
            ], "批量更新完成，{$newRecordsCount}个新记录");

        } catch (\Exception $e) {
            return $this->handleException($e, '批量更新个人最佳记录');
        }
    }

    /**
     * 删除个人最佳记录
     * 
     * DELETE /api/personal-bests/{exerciseId}
     */
    public function destroy(Request $request, string $exerciseId): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            $record = PersonalBest::forUser($user->id)
                ->forExercise($exerciseId)
                ->first();

            if (!$record) {
                return $this->fail('未找到该动作的个人最佳记录', 404);
            }

            $record->delete();

            return $this->success(null, '个人最佳记录删除成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '删除个人最佳记录');
        }
    }

    /**
     * 获取用户的力量排行榜（按估算1RM排序）
     * 
     * GET /api/personal-bests/leaderboard
     */
    public function leaderboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->fail('用户未认证', 401);
            }

            $records = PersonalBest::forUser($user->id)
                ->whereNotNull('estimated_1rm')
                ->where('estimated_1rm', '>', 0)
                ->orderBy('estimated_1rm', 'desc')
                ->limit(20)
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
