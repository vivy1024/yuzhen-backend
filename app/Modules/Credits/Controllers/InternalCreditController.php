<?php

namespace App\Modules\Credits\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Credits\Models\CreditLedger;
use App\Modules\Credits\Services\CreditService;
use App\Modules\Credits\Services\ModelPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * InternalCreditController - 积分系统 v2 内部 API 控制器
 *
 * 供 yuzhenfork (DAML-RAG) 调用，需要 internal.api 中间件认证
 *
 * API 端点：
 * - POST /api/internal/credits/deduct — AI 对话后扣减积分
 *
 * @version v2.0.0
 */
class InternalCreditController extends BaseController
{
    private CreditService $creditService;
    private ModelPricingService $pricingService;

    public function __construct(CreditService $creditService, ModelPricingService $pricingService)
    {
        $this->creditService = $creditService;
        $this->pricingService = $pricingService;
    }

    /**
     * AI 对话后扣减积分
     * POST /api/internal/credits/deduct
     *
     * body: { user_id, model, input_tokens, output_tokens, session_id }
     */
    public function deduct(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|min:1',
                'model' => 'required|string|max:100',
                'input_tokens' => 'required|integer|min:0',
                'output_tokens' => 'required|integer|min:0',
                'session_id' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                Log::warning('内部积分扣减请求参数无效', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all(),
                ]);
                return $this->fail('参数验证失败', 400, $validator->errors()->toArray());
            }

            $validated = $validator->validated();
            $userId = $validated['user_id'];
            $model = $validated['model'];
            $inputTokens = $validated['input_tokens'];
            $outputTokens = $validated['output_tokens'];
            $sessionId = $validated['session_id'] ?? null;

            // 计算 credits 消耗
            $creditsCost = $this->pricingService->calculateCreditsCost($model, $inputTokens, $outputTokens);

            // 免费模型不扣费
            if (bccomp($creditsCost, '0.000000', 6) <= 0) {
                // 仍然记录日志到 ai_request_logs
                $this->logAiRequest($userId, $model, $inputTokens, $outputTokens, '0.000000', $sessionId);

                return $this->success([
                    'credits_cost' => '0.000000',
                    'balance_after' => $this->creditService->getBalance($userId)['total_available'],
                    'model_tier' => $this->pricingService->getModelTier($model),
                    'free' => true,
                ], '免费模型，无需扣费');
            }

            // 执行扣减
            $result = $this->creditService->deduct($userId, $creditsCost, [
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'session_id' => $sessionId,
            ]);

            if (!$result['success']) {
                Log::warning('内部积分扣减失败：余额不足', [
                    'user_id' => $userId,
                    'model' => $model,
                    'credits_cost' => $creditsCost,
                    'available' => $result['available'] ?? null,
                ]);

                return response()->json([
                    'code' => 402,
                    'msg' => '积分不足',
                    'data' => [
                        'credits_cost' => $creditsCost,
                        'available' => $result['available'] ?? '0.000000',
                        'model_tier' => $this->pricingService->getModelTier($model),
                    ],
                ], 402);
            }

            // 记录 AI 请求日志
            $realCostUsd = $this->pricingService->calculateRealCostUsd($model, $inputTokens, $outputTokens);
            $this->logAiRequest($userId, $model, $inputTokens, $outputTokens, $creditsCost, $sessionId, $realCostUsd);

            Log::info('内部积分扣减成功', [
                'user_id' => $userId,
                'model' => $model,
                'credits_cost' => $creditsCost,
                'balance_after' => $result['balance_after'],
                'idempotent' => $result['idempotent'] ?? false,
            ]);

            return $this->success([
                'credits_cost' => $creditsCost,
                'balance_after' => $result['balance_after'],
                'ledger_id' => $result['ledger_id'],
                'model_tier' => $this->pricingService->getModelTier($model),
                'idempotent' => $result['idempotent'] ?? false,
            ], 'success');

        } catch (\Exception $e) {
            Log::error('内部积分扣减异常', [
                'user_id' => $request->input('user_id'),
                'model' => $request->input('model'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->fail('积分扣减失败，请稍后重试', 500);
        }
    }

    /**
     * 记录 AI 请求日志
     */
    private function logAiRequest(
        int $userId,
        string $model,
        int $inputTokens,
        int $outputTokens,
        string $creditsCost,
        ?string $sessionId,
        ?string $realCostUsd = null
    ): void {
        try {
            DB::table('ai_request_logs')->insert([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'real_cost_usd' => $realCostUsd ?? '0.00000000',
                'credits_cost' => $creditsCost,
                'status' => 'success',
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('AI请求日志写入失败（不影响扣费）', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
