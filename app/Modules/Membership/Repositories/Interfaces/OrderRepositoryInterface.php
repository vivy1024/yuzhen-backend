<?php

namespace App\Modules\Membership\Repositories\Interfaces;

interface OrderRepositoryInterface
{
    public function create(array $data): array;
    
    public function findById(int $id): ?array;
    
    public function findByOrderNo(string $orderNo): ?array;
    
    public function markAsPaid(int $id, string $tradeNo, string $payMethod): bool;
    
    public function cancel(int $id): bool;
    
    public function getRevenueByDate(string $date): float;
    
    public function getRevenueByMonth(string $month): float;
}

