<?php

namespace App\Http\Controllers\Internal;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * InternalCreditController - 内部积分API控制器
 * 
 * 用于DAML-RAG等内部服务记录积分消耗
 * 
 * 需要 X-Internal-Token 认证（通过 internal.api 中间件）
 * 
 * API端点：
 * - POST /api/internal/credits/record - 记录积分消耗
 * 
 * @version v1.0.0
 * @date 2026-02-05
 * @author 薛小川
 * @requirements 10.1
 */
class InternalCreditController extends BaseController
{
    /**
     * @var CreditService
     */
    protected CreditService $creditService;

    /**
     * 构造函数
     * 
     * @param CreditService $creditService
     */
    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    /**
     * 记录积分消耗（DAML-RAG调用）
     * POST /api/internal/credits/record
     * 
     * 此接口供DAML-RAG服务在DAG工作流完成后调用，
     * 记录Token消耗并扣除用户积分。
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * 请求格式：
     * {
     *     "user_id": 123,
     *     "tokens": 2500,
     *     "mode": "dag",
     *     "template_name": "exercise_optimization",
     *     "conversation_id": "conv_abc123",
     *     "input_tokens": 800,
     *     "output_tokens": 1700
     * }
     * 
     * 响应格式（成功）：
     * {
     *     "code": 200,
     *     "msg": "success",
     *     "data": {
     *         "transaction_id": 456,
     *         "credits_consumed": 3,
     *         "remaining_credits": 47
     *     }
     * }
     * 
     * 响应格式（积分不足）：
     * {
     *     "code": 402,
     *     "msg": "积分不足，当前剩余: 2，需要: 3",
     *     "data": {
     *         "remaining_credits": 2,
     *         "required_credits": 3,
     *         "membership_tier": "free"
     *     }
     * }
     * 
     * @requirements 10.1, 10.2, 10.3, 10.4, 10.5
     */
    public function recordConsumption(Request $request): JsonResponse
    {
        try {
            // 1. 验证请求参数
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|min:1',
                'tokens' => 'required|integer|min:0',
                'mode' => 'required|string|in:dag,agent',
                'template_name' => 'nullable|string|max:100',
                'conversation_id' => 'nullable|string|max:100',
                'input_tokens' => 'nullable|integer|min:0',
                'output_tokens' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                Log::warning('DAML-RAG积分记录请求参数无效', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->except(['password']),
                ]);
                
                return $this->fail('参数验证失败', 400, $validator->errors()->toArray());
            }

            $validated = $validator->validated();
            $userId = $validated['user_id'];
            $tokens = $validated['tokens'];
            $mode = $validated['mode'];

            // 2. 计算积分消耗（无副作用，可在事务外执行）
            $creditsToConsume = $this->creditService->calculateCredits($tokens, $mode);

            // 3. 快速检查积分是否足够（避免不必要的事务开销）
            $checkResult = $this->creditService->checkSufficientCredits($userId, $creditsToConsume);

            if (!$checkResult['sufficient']) {
                Log::warning('DAML-RAG积分记录失败：积分不足', [
                    'user_id' => $userId,
                    'tokens' => $tokens,
                    'mode' => $mode,
                    'required_credits' => $creditsToConsume,
                    'remaining_credits' => $checkResult['remaining'],
                ]);

                return response()->json([
                    'code' => 402,
                    'msg' => $checkResult['message'],
                    'data' => [
                        'remaining_credits' => $checkResult['remaining'],
                        'required_credits' => $creditsToConsume,
                        'membership_tier' => $checkResult['membership_tier'] ?? 'free',
                    ],
                ], 402);
            }

            // 4. 事务内执行：幂等性检查 + 积分扣减（原子化，防止TOCTOU竞态）
            $result = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $userId, $tokens, $mode) {
                // 幂等性检查（lockForUpdate防止并发重复插入）
                if (!empty($validated['conversation_id'])) {
                    $existing = \App\Models\CreditTransaction::where('conversation_id', $validated['conversation_id'])
                        ->where('user_id', $userId)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        return ['idempotent' => true, 'transaction' => $existing];
                    }
                }

                // 记录积分消耗（CreditService::recordTransaction内部有lockForUpdate）
                $transaction = $this->creditService->recordTransaction($userId, [
                    'tokens' => $tokens,
                    'mode' => $mode,
                    'template_name' => $validated['template_name'] ?? null,
                    'conversation_id' => $validated['conversation_id'] ?? null,
                    'input_tokens' => $validated['input_tokens'] ?? 0,
                    'output_tokens' => $validated['output_tokens'] ?? 0,
                    'description' => "DAML-RAG {$mode}模式消耗",
                ]);

                return ['idempotent' => false, 'transaction' => $transaction];
            });

            // 5. 获取余额并返回
            $balance = $this->creditService->getBalance($userId);
            $transaction = $result['transaction'];

            if ($result['idempotent']) {
                Log::info('DAML-RAG积分记录跳过（幂等）', [
                    'user_id' => $userId,
                    'conversation_id' => $validated['conversation_id'],
                    'existing_transaction_id' => $transaction->id,
                ]);
            } else {
                Log::info('DAML-RAG积分记录成功', [
                    'user_id' => $userId,
                    'transaction_id' => $transaction->id,
                    'credits_consumed' => $transaction->credits,
                    'remaining_credits' => $balance['remaining'],
                ]);
            }

            return $this->success([
                'transaction_id' => $transaction->id,
                'credits_consumed' => $transaction->credits,
                'remaining_credits' => $balance['remaining'],
                'idempotent' => $result['idempotent'],
            ], 'success');

        } catch (\Exception $e) {
            // 记录错误但不阻塞响应（Requirements 10.5）
            Log::error('DAML-RAG积分记录异常', [
                'user_id' => $request->input('user_id'),
                'tokens' => $request->input('tokens'),
                'mode' => $request->input('mode'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 返回通用错误消息，不泄露内部异常详情
            return $this->fail('积分记录失败，请稍后重试', 500);
        }
    }
}
