<?php

namespace App\Modules\Membership\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Membership\Models\Order;
use App\Modules\Membership\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理员订单控制器
 * 
 * 用于审核用户上传的支付截图
 */
class AdminOrderController extends BaseController
{
    protected MembershipService $membershipService;

    public function __construct(MembershipService $membershipService)
    {
        $this->membershipService = $membershipService;
    }

    /**
     * 获取待审核订单列表
     * 
     * GET /api/admin/orders/pending
     */
    public function pendingOrders(Request $request): JsonResponse
    {
        try {
            $orders = Order::where('status', Order::STATUS_REVIEWING)
                ->with(['user:id,name,email,avatar', 'membership:id,name,price'])
                ->orderBy('proof_uploaded_at', 'asc')
                ->paginate(20);
            
            return $this->success([
                'orders' => $orders->items(),
                'total' => $orders->total(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ], '获取待审核订单成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取待审核订单');
        }
    }

    /**
     * 获取所有订单列表
     * 
     * GET /api/admin/orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Order::with(['user:id,name,email,avatar', 'membership:id,name,price']);
            
            // 状态筛选
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            // 搜索
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('order_no', 'like', "%{$search}%")
                      ->orWhereHas('user', function($q2) use ($search) {
                          $q2->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }
            
            $orders = $query->orderBy('created_at', 'desc')->paginate(20);
            
            return $this->success([
                'orders' => $orders->items(),
                'total' => $orders->total(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ], '获取订单列表成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取订单列表');
        }
    }

    /**
     * 获取订单详情
     * 
     * GET /api/admin/orders/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $order = Order::with(['user:id,name,email,avatar,phone', 'membership'])
                ->findOrFail($id);
            
            return $this->success($order, '获取订单详情成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取订单详情');
        }
    }

    /**
     * 审核通过
     * 
     * POST /api/admin/orders/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $order = Order::findOrFail($id);
            
            if ($order->status !== Order::STATUS_REVIEWING) {
                return $this->fail('订单状态不允许审核');
            }
            
            $reviewerId = auth()->id();
            $note = $request->input('note', '审核通过');
            
            // 审核通过
            $order->approvePayment($reviewerId, $note);
            
            // 激活会员
            $this->membershipService->activateMembershipByOrder($order->id);
            
            return $this->success([
                'order_no' => $order->order_no,
                'status' => $order->status,
            ], '审核通过，会员已激活');

        } catch (\Exception $e) {
            return $this->handleException($e, '审核订单');
        }
    }

    /**
     * 审核拒绝
     * 
     * POST /api/admin/orders/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'note' => 'required|string|min:5|max:500',
            ], [
                'note.required' => '请填写拒绝原因',
                'note.min' => '拒绝原因至少5个字符',
            ]);
            
            $order = Order::findOrFail($id);
            
            if ($order->status !== Order::STATUS_REVIEWING) {
                return $this->fail('订单状态不允许审核');
            }
            
            $reviewerId = auth()->id();
            $note = $request->input('note');
            
            // 审核拒绝
            $order->rejectPayment($reviewerId, $note);
            
            return $this->success([
                'order_no' => $order->order_no,
                'status' => $order->status,
            ], '已拒绝，用户可重新上传截图');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->handleException($e, '拒绝订单');
        }
    }

    /**
     * 获取订单统计
     * 
     * GET /api/admin/orders/stats
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'pending' => Order::where('status', Order::STATUS_PENDING)->count(),
                'reviewing' => Order::where('status', Order::STATUS_REVIEWING)->count(),
                'paid' => Order::where('status', Order::STATUS_PAID)->count(),
                'cancelled' => Order::where('status', Order::STATUS_CANCELLED)->count(),
                'total_revenue' => Order::where('status', Order::STATUS_PAID)->sum('amount'),
                'today_revenue' => Order::where('status', Order::STATUS_PAID)
                    ->whereDate('paid_at', today())
                    ->sum('amount'),
            ];
            
            return $this->success($stats, '获取统计成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取统计');
        }
    }
}
