<?php

namespace App\Modules\Credits\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * AdminApiKeyController - 管理员 API Key 状态控制器
 *
 * API 端点：
 * - GET /api/admin/api-keys — API Key 列表
 * - PUT /api/admin/api-keys/{id} — 更新 Key 状态
 *
 * 中间件: jwt.auth + admin
 *
 * @version v1.0.0
 */
class AdminApiKeyController extends BaseController
{
    /**
     * API Key 列表
     * GET /api/admin/api-keys
     */
    public function list(): JsonResponse
    {
        try {
            $keys = DB::table('api_key_status')
                ->select([
                    'id',
                    'provider',
                    'key_name',
                    'balance',
                    'balance_unit',
                    'total_limit',
                    'last_checked_at',
                    'status',
                    'alert_threshold',
                    'created_at',
                    'updated_at',
                ])
                ->orderBy('provider')
                ->orderBy('key_name')
                ->get();

            // 统计概览
            $summary = [
                'total' => $keys->count(),
                'active' => $keys->where('status', 'active')->count(),
                'low_balance' => $keys->where('status', 'low_balance')->count(),
                'exhausted' => $keys->where('status', 'exhausted')->count(),
                'error' => $keys->where('status', 'error')->count(),
            ];

            return $this->success([
                'items' => $keys,
                'summary' => $summary,
            ], '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取API Key列表');
        }
    }

    /**
     * 更新 Key 状态（手动刷新余额后更新）
     * PUT /api/admin/api-keys/{id}
     * body: { balance, status }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'balance' => 'nullable|numeric|min:0',
                'status' => 'nullable|string|in:active,low_balance,exhausted,error',
                'alert_threshold' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败', 422, $validator->errors()->toArray());
            }

            $key = DB::table('api_key_status')->where('id', $id)->first();
            if (!$key) {
                return $this->fail('API Key 记录不存在', 404);
            }

            $validated = $validator->validated();
            $updateData = ['updated_at' => now()];

            if (isset($validated['balance'])) {
                $updateData['balance'] = $validated['balance'];
                $updateData['last_checked_at'] = now();
            }
            if (isset($validated['status'])) {
                $updateData['status'] = $validated['status'];
            }
            if (isset($validated['alert_threshold'])) {
                $updateData['alert_threshold'] = $validated['alert_threshold'];
            }

            DB::table('api_key_status')->where('id', $id)->update($updateData);

            Log::info('管理员更新API Key状态', [
                'admin_id' => auth()->id(),
                'key_id' => $id,
                'provider' => $key->provider,
                'key_name' => $key->key_name,
                'changes' => $validated,
            ]);

            $updated = DB::table('api_key_status')->where('id', $id)->first();

            return $this->success($updated, '更新成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '更新API Key状态');
        }
    }
}
