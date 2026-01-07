<?php

namespace App\Modules\Membership\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User Membership Resource v2.0
 * 
 * 用户会员资源转换器 - 完整会员信息
 * 完全对齐前端 TypeScript 类型定义
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class UserMembershipResource extends JsonResource
{
    public function toArray($request): array
    {
        // ✅ 兼容数组和对象两种数据格式
        $data = is_array($this->resource) ? $this->resource : $this;
        
        return [
            'user_id' => $data['user_id'] ?? null,
            'membership_id' => $data['membership_id'] ?? null,
            'tier' => $data['tier'] ?? 'free',
            'tier_name' => $data['tier_name'] ?? '免费用户',
            'start_date' => $data['started_at'] ?? $data['start_date'] ?? null,
            'end_date' => $data['expires_at'] ?? $data['end_date'] ?? null,
            'expires_at' => $data['expires_at'] ?? $data['end_date'] ?? null,
            'auto_renew' => (bool) ($data['auto_renew'] ?? false),
            'features' => $data['features'] ?? [],
            'limits' => $data['limits'] ?? [
                'monthly_requests' => 0,
                'max_plans' => 0,
                'ai_chat' => false,
                'advanced_analytics' => false,
            ],
            'usage_stats' => $data['usage_stats'] ?? [
                'requests_this_month' => 0,
                'total_requests' => 0,
                'last_request_date' => null,
            ],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'remaining_days' => $data['remaining_days'] ?? 0,
            'created_at' => $data['created_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
        ];
    }
}

