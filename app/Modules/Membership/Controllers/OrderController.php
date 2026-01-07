<?php

namespace App\Modules\Membership\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Membership\Services\MembershipService;
use App\Modules\Membership\Requests\CreateOrderRequest;
use App\Modules\Membership\Resources\OrderResource;
use App\Modules\Membership\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Order Controller
 * 
 * 订单控制器
 * 支持收款码+截图上传的打赏支付方式
 */
class OrderController extends BaseController
{
    protected MembershipService $membershipService;

    public function __construct(MembershipService $membershipService)
    {
        $this->membershipService = $membershipService;
    }

    /**
     * 创建订单
     * 
     * POST /api/membership/orders
     */
    public function create(CreateOrderRequest $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $membershipId = $request->validated()['membership_id'];

            $order = $this->membershipService->createOrder($userId, $membershipId);

            return $this->success(
                new OrderResource($order),
                '订单创建成功'
            );

        } catch (\Exception $e) {
            return $this->handleException($e, '创建订单');
        }
    }

    /**
     * 查询订单
     * 
     * GET /api/membership/orders/{orderNo}
     */
    public function show(string $orderNo): JsonResponse
    {
        try {
            $userId = auth()->id();
            $order = Order::where('order_no', $orderNo)
                ->where('user_id', $userId)
                ->first();
            
            if (!$order) {
                return $this->fail('订单不存在', 404);
            }

            return $this->success(new OrderResource($order), '查询订单成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '查询订单');
        }
    }

    /**
     * 上传支付截图
     * 
     * POST /api/membership/orders/{orderNo}/upload-proof
     */
    public function uploadPaymentProof(Request $request, string $orderNo): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            // 验证请求
            $request->validate([
                'payment_proof' => 'required|image|max:5120', // 最大5MB
                'pay_method' => 'required|in:wechat,alipay',
            ], [
                'payment_proof.required' => '请上传支付截图',
                'payment_proof.image' => '请上传图片格式文件',
                'payment_proof.max' => '图片大小不能超过5MB',
                'pay_method.required' => '请选择支付方式',
                'pay_method.in' => '支付方式无效',
            ]);
            
            // 查找订单
            $order = Order::where('order_no', $orderNo)
                ->where('user_id', $userId)
                ->first();
            
            if (!$order) {
                return $this->fail('订单不存在', 404);
            }
            
            // 检查订单状态
            if (!in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_REVIEWING])) {
                return $this->fail('订单状态不允许上传截图');
            }
            
            // 保存截图
            $file = $request->file('payment_proof');
            $filename = 'payment_proofs/' . $orderNo . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('public', $filename);
            
            // 生成完整的URL（使用APP_URL）
            $url = config('app.url') . '/storage/' . $filename;
            
            // 更新订单状态
            $order->uploadPaymentProof($url, $request->pay_method);
            
            return $this->success([
                'order_no' => $order->order_no,
                'status' => $order->status,
                'payment_proof_url' => $url,
                'message' => '截图上传成功，请等待审核',
            ], '上传成功');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->handleException($e, '上传支付截图');
        }
    }

    /**
     * 获取收款码
     * 
     * GET /api/membership/payment-qrcodes
     */
    public function getPaymentQRCodes(): JsonResponse
    {
        try {
            // 返回收款码URL（存放在public目录）
            // 使用英文文件名避免URL编码问题
            $baseUrl = 'http://localhost:8000';
            
            return $this->success([
                'wechat' => $baseUrl . '/wechat-qrcode.jpg',
                'alipay' => $baseUrl . '/alipay-qrcode.jpg',
            ], '获取收款码成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取收款码');
        }
    }

    /**
     * 取消订单
     * 
     * POST /api/membership/orders/{orderId}/cancel
     */
    public function cancel(int $orderId): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            $result = $this->membershipService->cancelOrder($orderId, $userId);

            if ($result) {
                return $this->success(null, '订单已取消');
            }

            return $this->fail('订单取消失败');

        } catch (\Exception $e) {
            return $this->handleException($e, '取消订单');
        }
    }

    /**
     * 删除订单
     * 
     * DELETE /api/membership/orders/{orderId}
     * 仅允许删除待支付状态的订单
     */
    public function delete(int $orderId): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            // 查找订单
            $order = Order::where('id', $orderId)
                ->where('user_id', $userId)
                ->first();
            
            if (!$order) {
                return $this->fail('订单不存在', 404);
            }
            
            // 只允许删除待支付订单
            if ($order->status !== Order::STATUS_PENDING) {
                return $this->fail('只能删除待支付订单');
            }
            
            // 删除订单
            $order->delete();
            
            return $this->success(null, '订单已删除');

        } catch (\Exception $e) {
            return $this->handleException($e, '删除订单');
        }
    }

    /**
     * 获取用户订单列表
     * 
     * GET /api/membership/orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            $orders = Order::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->paginate(10);
            
            return $this->success([
                'orders' => OrderResource::collection($orders),
                'total' => $orders->total(),
            ], '获取订单列表成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取订单列表');
        }
    }

    /**
     * 获取支付截图（图片代理）
     * 
     * GET /api/membership/orders/{orderNo}/proof-image
     * 通过 API 返回图片，绕过浏览器 ORB 限制
     */
    public function getProofImage(string $orderNo)
    {
        try {
            // 查找订单
            $order = Order::where('order_no', $orderNo)->first();
            
            if (!$order || !$order->payment_proof_url) {
                abort(404, '图片不存在');
            }
            
            // 从 URL 中提取文件路径
            $url = $order->payment_proof_url;
            $path = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));
            
            // 检查文件是否存在
            if (!Storage::disk('public')->exists($path)) {
                abort(404, '图片文件不存在');
            }
            
            // 获取文件内容和 MIME 类型
            $file = Storage::disk('public')->get($path);
            $mimeType = Storage::disk('public')->mimeType($path);
            
            // 返回图片响应
            return response($file, 200)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=86400');
                
        } catch (\Exception $e) {
            abort(500, '获取图片失败');
        }
    }
}

