<?php

namespace App\Modules\Membership\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Membership Resource v2.0
 * 
 * 会员资源转换器 - 添加tier和limits字段
 * 完全对齐前端 TypeScript 类型定义
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class MembershipResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'slug' => $this['slug'],
            'tier' => $this['tier'] ?? 'free',
            'price' => (float) $this['price'],
            'duration_days' => $this['duration_days'],
            'description' => $this['description'],
            'features' => $this['features'] ?? [],
            'limits' => $this['limits'] ?? [
                'monthly_requests' => -1,
                'exercises_per_plan' => -1,
                'plan_complexity' => 'basic',
                'support_priority' => 'normal',
            ],
            'is_active' => (bool) $this['is_active'],
            'sort_order' => $this['sort_order'] ?? 0,
        ];
    }
}

