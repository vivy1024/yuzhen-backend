<?php

namespace App\Modules\Credits\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Credits\Models\CreditLedger;
use App\Modules\Credits\Models\ModelPricing;
use App\Modules\Credits\Models\UserCreditAccount;
use App\Modules\Credits\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * AdminCreditController - 管理员积分管理控制器
 *
 * API 端点：
 * - GET  /api/admin/credits/overview — 积分总览统计
 * - GET  /api/admin/credits/users — 用户积分列表（分页+搜索）
 * - POST /api/admin/credits/adjust — 手动调整用户积分
 * - GET  /api/admin/credits/pricing — 模型定价列表
 * - PUT  /api/admin/credits/pricing/{id} — 更新模型定价
 *
 * 中间件: jwt.auth + admin
 *
 * @version v1.0.0
 */
class AdminCreditController extends BaseController
{
    private CreditService $creditService;

    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    /**
     * 积分总览统计
     * GET /api/admin/credits/overview
     *
     * 返回：total_issued, total_consumed, total_circulation, user_count_by_tier
     */
    public function overview(): JsonResponse
    {
        try {
            // 总发放（earn + bonus + admin_adjust 正数）
            $totalIssued = DB::table('credit_ledger')
                ->where('amount', '>', 0)
                ->sum('amount');

            // 总消耗（spend 的绝对值）
            $totalConsumed = DB::table('credit_ledger')
                ->where('type', 'spend')
                ->sum(DB::raw('ABS(amount)'));

            // 当前流通（所有账户余额总和）
            $totalCirculation = DB::table('user_credit_accounts')
                ->sum(DB::raw('balance + bonus_balance'));

            // 按 tier 统计用户数
            $userCountByTier = DB::table('user_credit_accounts')
                ->select('tier', DB::raw('COUNT(*) as count'))
                ->groupBy('tier')
                ->pluck('count', 'tier')
                ->toArray();

            // 总账户数
            $totalAccounts = DB::table('user_credit_accounts')->count();

            return $this->success([
                'total_issued' => number_format((float) $totalIssued, 6, '.', ''),
                'total_consumed' => number_format((float) $totalConsumed, 6, '.', ''),
                'total_circulation' => number_format((float) $totalCirculation, 6, '.', ''),
                'total_accounts' => $totalAccounts,
                'user_count_by_tier' => $userCountByTier,
            ], '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取积分总览');
        }
    }

    /**
     * 用户积分列表（分页+搜索）
     * GET /api/admin/credits/users?search=&tier=&page=&per_page=
     */
    public function userList(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'search' => 'nullable|string|max:100',
                'tier' => 'nullable|string|in:free,warmheart,energy',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败', 422, $validator->errors()->toArray());
            }

            $validated = $validator->validated();
            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 20;

            $query = DB::table('user_credit_accounts as uca')
                ->join('users', 'users.id', '=', 'uca.user_id')
                ->select([
                    'uca.id',
                    'uca.user_id',
                    'users.name',
                    'users.email',
                    'users.membership_tier',
                    'uca.balance',
                    'uca.bonus_balance',
                    'uca.monthly_limit',
                    'uca.used_this_month',
                    'uca.tier',
                    'uca.last_reset_at',
                    'uca.created_at',
                ]);

            // 搜索（用户名或邮箱）
            if (!empty($validated['search'])) {
                $search = $validated['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%");
                });
            }

            // 按 tier 筛选
            if (!empty($validated['tier'])) {
                $query->where('uca.tier', $validated['tier']);
            }

            $total = $query->count();
            $items = $query
                ->orderByDesc('uca.balance')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get()
                ->map(function ($item) {
                    $item->total_available = number_format(
                        (float) $item->balance + (float) $item->bonus_balance,
                        6, '.', ''
                    );
                    return $item;
                });

            return $this->success([
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ], '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取用户积分列表');
        }
    }

    /**
     * 手动调整用户积分
     * POST /api/admin/credits/adjust
     * body: { user_id, amount, reason }
     */
    public function adjustCredits(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'amount' => 'required|numeric|not_in:0',
                'reason' => 'required|string|max:255',
            ], [
                'user_id.exists' => '用户不存在',
                'amount.not_in' => '调整金额不能为0',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败', 422, $validator->errors()->toArray());
            }

            $validated = $validator->validated();
            $userId = $validated['user_id'];
            $amount = $validated['amount'];
            $reason = $validated['reason'];
            $adminId = auth()->id();

            // 正数=增加，负数=扣减
            if ($amount > 0) {
                $result = $this->creditService->earn(
                    $userId,
                    number_format(abs($amount), 6, '.', ''),
                    CreditLedger::SOURCE_ADMIN,
                    "管理员调整: {$reason}"
                );
            } else {
                $result = $this->creditService->deduct(
                    $userId,
                    number_format(abs($amount), 6, '.', ''),
                    [
                        'source' => CreditLedger::SOURCE_ADMIN,
                        'description' => "管理员调整: {$reason}",
                    ]
                );

                if (!$result['success']) {
                    return $this->fail('扣减失败：' . ($result['reason'] ?? '余额不足'), 422, [
                        'available' => $result['available'] ?? '0.000000',
                    ]);
                }
            }

            Log::info('管理员调整积分', [
                'admin_id' => $adminId,
                'user_id' => $userId,
                'amount' => $amount,
                'reason' => $reason,
            ]);

            return $this->success([
                'user_id' => $userId,
                'amount' => $amount,
                'balance_after' => $result['balance_after'],
                'ledger_id' => $result['ledger_id'],
            ], '调整成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '调整用户积分');
        }
    }

    /**
     * 模型定价列表
     * GET /api/admin/credits/pricing
     */
    public function pricingList(): JsonResponse
    {
        try {
            $pricing = DB::table('model_pricing')
                ->select([
                    'id',
                    'model_name',
                    'input_price_per_ktoken',
                    'output_price_per_ktoken',
                    'real_input_cost_usd',
                    'real_output_cost_usd',
                    'cost_tier',
                    'enabled',
                    'updated_at',
                ])
                ->orderBy('cost_tier')
                ->orderBy('model_name')
                ->get();

            return $this->success($pricing, '获取成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '获取模型定价列表');
        }
    }

    /**
     * 更新模型定价
     * PUT /api/admin/credits/pricing/{id}
     * body: { input_price_per_ktoken, output_price_per_ktoken }
     */
    public function updatePricing(Request $request, int $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'input_price_per_ktoken' => 'required|numeric|min:0',
                'output_price_per_ktoken' => 'required|numeric|min:0',
                'real_input_cost_usd' => 'nullable|numeric|min:0',
                'real_output_cost_usd' => 'nullable|numeric|min:0',
                'cost_tier' => 'nullable|string|in:free,low,free_quota,baseline',
                'enabled' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return $this->fail('参数验证失败', 422, $validator->errors()->toArray());
            }

            $pricing = ModelPricing::find($id);
            if (!$pricing) {
                return $this->fail('定价记录不存在', 404);
            }

            $validated = $validator->validated();
            $pricing->input_price_per_ktoken = $validated['input_price_per_ktoken'];
            $pricing->output_price_per_ktoken = $validated['output_price_per_ktoken'];

            if (isset($validated['real_input_cost_usd'])) {
                $pricing->real_input_cost_usd = $validated['real_input_cost_usd'];
            }
            if (isset($validated['real_output_cost_usd'])) {
                $pricing->real_output_cost_usd = $validated['real_output_cost_usd'];
            }
            if (isset($validated['cost_tier'])) {
                $pricing->cost_tier = $validated['cost_tier'];
            }
            if (isset($validated['enabled'])) {
                $pricing->enabled = $validated['enabled'];
            }

            $pricing->save();

            Log::info('管理员更新模型定价', [
                'admin_id' => auth()->id(),
                'pricing_id' => $id,
                'model_name' => $pricing->model_name,
                'changes' => $validated,
            ]);

            return $this->success($pricing, '更新成功');
        } catch (\Exception $e) {
            return $this->handleException($e, '更新模型定价');
        }
    }
}
