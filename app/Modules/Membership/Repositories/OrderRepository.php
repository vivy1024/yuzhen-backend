<?php

namespace App\Modules\Membership\Repositories;

use App\Modules\Membership\Repositories\Interfaces\OrderRepositoryInterface;
use App\Modules\Membership\Models\Order;
use App\Infrastructure\Database\Repositories\BaseRepository;

class OrderRepository extends BaseRepository implements OrderRepositoryInterface
{
    protected $model = Order::class;

    public function create(array $data): array
    {
        $order = Order::create($data);
        return $order->toArray();
    }

    public function findById(int $id): ?array
    {
        $order = Order::find($id);
        return $order ? $order->toArray() : null;
    }

    public function findByOrderNo(string $orderNo): ?array
    {
        $order = Order::where('order_no', $orderNo)->first();
        return $order ? $order->toArray() : null;
    }

    public function markAsPaid(int $id, string $tradeNo, string $payMethod): bool
    {
        $order = Order::find($id);
        
        if (!$order) {
            return false;
        }

        $order->markAsPaid($tradeNo, $payMethod);
        return true;
    }

    public function cancel(int $id): bool
    {
        $order = Order::find($id);
        
        if (!$order) {
            return false;
        }

        $order->cancel();
        return true;
    }

    public function getRevenueByDate(string $date): float
    {
        return Order::paid()
            ->whereDate('paid_at', $date)
            ->sum('actual_amount');
    }

    public function getRevenueByMonth(string $month): float
    {
        return Order::paid()
            ->where('paid_at', 'like', $month . '%')
            ->sum('actual_amount');
    }
}

