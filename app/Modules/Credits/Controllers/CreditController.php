<?php

namespace App\Modules\Credits\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Credits\Models\CreditLedger;
use App\Modules\Credits\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CreditController - 积分系统 v2 用户端控制器
 *
 * API 端点：
 * - GET /api/credits/balance — 查询当前用户余额
 * - GET /api/credits/transactions — 查询流水（分页）
 * - POST /api/credits/checkin — 每日签到
 *
 * 所有接口需要 jwt.auth 中间件
 *
 * @version v2.0.0
 */
class CreditController extends BaseController
{
    private CreditService $creditService;

    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    /**
     * 查询当前用户余额
     * GET /api/credits/balance
     */
    public function balance(): JsonResponse
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return $this->fail('用户未登录', 401);
            }

            $balance = $this->creditService->getBalance($userId);

            return $this->success($balance, '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取积分余额');
        }
    }

    /**
     * 查询流水（分页）
     * GET /api/credits/transactions
     *
     * 参数：page, per_page, type
     */
    public function transactions(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return $this->fail('用户未登录', 401);
            }

            $validated = $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:50',
                'type' => 'nullable|string|in:earn,spend,reset,bonus,admin_adjust',
            ]);

            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 15;

            $query = CreditLedger::forUser($userId)->orderByDesc('created_at');

            if (!empty($validated['type'])) {
                $query->ofType($validated['type']);
            }

            $total = $query->count();
            $items = $query
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get()
                ->map(fn(CreditLedger $item) => [
                    'id' => $item->id,
                    'type' => $item->type,
                    'type_label' => $item->type_label,
                    'amount' => $item->amount,
                    'balance_after' => $item->balance_after,
                    'model' => $item->model,
                    'input_tokens' => $item->input_tokens,
                    'output_tokens' => $item->output_tokens,
                    'source' => $item->source,
                    'description' => $item->description,
                    'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                ]);

            return $this->success([
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ], '获取成功');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('参数验证失败', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, '获取积分流水');
        }
    }

    /**
     * 每日签到
     * POST /api/credits/checkin
     */
    public function checkin(): JsonResponse
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return $this->fail('用户未登录', 401);
            }

            // 检查今日是否已签到
            $today = now()->toDateString();
            $alreadyCheckedIn = \DB::table('daily_checkins')
                ->where('user_id', $userId)
                ->where('checkin_date', $today)
                ->exists();

            if ($alreadyCheckedIn) {
                return $this->fail('今日已签到', 422);
            }

            // 计算连续签到天数
            $yesterday = now()->subDay()->toDateString();
            $lastCheckin = \DB::table('daily_checkins')
                ->where('user_id', $userId)
                ->where('checkin_date', $yesterday)
                ->first();

            $streakDays = $lastCheckin ? ($lastCheckin->streak_days + 1) : 1;

            // 签到奖励：基础 2 credits，连续签到每天多 0.5，上限 5
            $baseReward = '2.000000';
            $streakBonus = bcmul(bcsub((string) min($streakDays, 7), '1', 6), '0.500000', 6);
            $creditsEarned = bcadd($baseReward, $streakBonus, 6);
            $creditsEarned = bccomp($creditsEarned, '5.000000', 6) > 0 ? '5.000000' : $creditsEarned;

            // 记录签到
            \DB::table('daily_checkins')->insert([
                'user_id' => $userId,
                'checkin_date' => $today,
                'streak_days' => $streakDays,
                'credits_earned' => $creditsEarned,
            ]);

            // 发放积分
            $result = $this->creditService->earn(
                $userId,
                $creditsEarned,
                'checkin',
                "每日签到（连续{$streakDays}天）"
            );

            Log::info('用户签到成功', [
                'user_id' => $userId,
                'streak_days' => $streakDays,
                'credits_earned' => $creditsEarned,
            ]);

            return $this->success([
                'credits_earned' => $creditsEarned,
                'streak_days' => $streakDays,
                'balance_after' => $result['balance_after'],
            ], '签到成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '每日签到');
        }
    }
}
