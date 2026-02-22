<?php

namespace App\Modules\User\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User Profile Resource v2.0
 *
 * 用户档案资源转换器 - 直接返回UserProfile结构
 * 完全对齐前端 TypeScript 类型定义
 *
 * @version 2.0.0
 * @date 2025-11-08
 */
class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->resource;

        return [
            // 用户ID
            'user_id' => $profile->user_id,

            // 基础信息（必填）
            'basic_info' => $profile->basic_info ?? null,

            // 健身目标（必填）
            'fitness_goals' => $profile->fitness_goals ?? null,

            // 训练偏好（必填）
            'training_preferences' => $profile->training_preferences ?? null,

            // 首选休息模式（可选）
            'preferred_rest_pattern' => $profile->preferred_rest_pattern ?? null,

            // 力量数据（非初学者才有）
            'strength_data' => $profile->strength_data ?? null,

            // 健康状况（必填）
            'health_status' => $profile->health_status ?? null,

            // 营养档案（必填）
            'nutrition_profile' => $profile->nutrition_profile ?? null,

            // FFMI评估结果（可选）
            'ffmi_assessment' => $profile->ffmi_assessment ?? null,

            // 训练连续天数与成就
            'streak_days' => $profile->streak_days ?? 0,
            'total_training_days' => $profile->total_training_days ?? 0,
            'last_training_date' => $profile->last_training_date?->toDateString(),
            'achievements' => $profile->getAchievements(),

            // 创建时间 ISO 8601
            'created_at' => $profile->created_at?->toISOString(),

            // 更新时间 ISO 8601
            'updated_at' => $profile->updated_at?->toISOString(),

            // 版本号（用于冲突检测）
            'version' => $profile->version ?? 1,
        ];
    }
}