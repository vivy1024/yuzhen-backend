<?php

namespace App\Modules\Membership\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this['id'],
            'order_no' => $this['order_no'],
            'membership' => isset($this['membership']) ? new MembershipResource($this['membership']) : null,
            'amount' => (float) $this['amount'],
            'actual_amount' => (float) $this['actual_amount'],
            'discount_amount' => (float) $this['discount_amount'],
            'status' => $this['status'],
            'pay_method' => $this['pay_method'] ?? null,
            'created_at' => $this['created_at'],
            'paid_at' => $this['paid_at'] ?? null,
        ];
    }
}

