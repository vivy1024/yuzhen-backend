<?php

namespace App\Modules\User\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User Detail Resource v2.0
 * 
 * 用户详情资源转换器 - 完整JSON结构
 * 完全对齐前端 TypeScript 类型定义
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class UserDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone ?? null,
            'avatar' => $this->avatar ?? null,
            'role' => $this->role,
            'status' => $this->status,
            
            // 用户档案信息（v2.0 完整JSON结构）
                    'profile' => $this->when($this->relationLoaded('profile') && $this->profile, function () {
                        return [
                            'user_id' => $this->profile->user_id,
                            'basic_info' => $this->profile->basic_info ?? null,
                            'fitness_goals' => $this->profile->fitness_goals ?? null,
                            'training_preferences' => $this->profile->training_preferences ?? null,
                            'strength_data' => $this->profile->strength_data ?? null,
                            'health_status' => $this->profile->health_status ?? null,
                            'nutrition_profile' => $this->profile->nutrition_profile ?? null,
                            'ffmi_assessment' => $this->profile->ffmi_assessment ?? null,
                            'version' => $this->profile->version ?? 1,
                            'created_at' => $this->profile->created_at?->toISOString(),
                            'updated_at' => $this->profile->updated_at?->toISOString(),
                        ];
                    }),
            
            // 会员信息（v2.1 - 修复会员数据获取）
            'membership' => $this->when($this->relationLoaded('membership') && $this->membership, function () {
                $userMembership = $this->membership;  // UserMembership对象
                $membership = $userMembership->membership;  // Membership对象
                
                return [
                    // 会员等级信息
                    'id' => $membership->id ?? null,
                    'name' => $membership->name ?? '免费版',
                    'slug' => $membership->slug ?? 'free',
                    'tier' => $membership->tier ?? 'free',
                    'price' => $membership->price ?? 0,
                    
                    // 用户会员状态
                    'is_active' => (bool) ($userMembership->is_active ?? false),
                    'started_at' => $userMembership->started_at?->toISOString(),
                    'expires_at' => $userMembership->expires_at?->toISOString(),
                    'remaining_days' => $userMembership->remainingDays() ?? 0,
                    
                    // 会员权益
                    'features' => $membership->features ?? [],
                    'limits' => $membership->limits ?? [],
                    'unlock_all_exercises' => (bool) ($membership->unlock_all_exercises ?? false),
                    'ai_recommendation' => (bool) ($membership->ai_recommendation ?? false),
                    'data_analysis' => (bool) ($membership->data_analysis ?? false),
                    'max_training_plans' => $membership->max_training_plans ?? 3,
                ];
            }),
            
            // 时间戳
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

