<?php

namespace App\Modules\Membership\Services;

use Illuminate\Support\Facades\Cache;

/**
 * 会员配置服务
 * 
 * 统一管理会员系统开关和用户限制
 * 
 * 当会员系统禁用时（MEMBERSHIP_SYSTEM_ENABLED=false）：
 * - 所有用户使用统一限制
 * - 隐藏会员购买入口
 * - 前端显示打赏模式
 * 
 * @version v1.0.0
 * @date 2026-01-09
 */
class MembershipConfigService
{
    /**
     * 检查会员系统是否启用
     */
    public function isEnabled(): bool
    {
        return config('membership.enabled', false);
    }

    /**
     * 获取用户的使用限制
     * 
     * 如果会员系统禁用，返回统一限制
     * 如果会员系统启用，根据用户会员等级返回对应限制
     */
    public function getUserLimits(int $userId): array
    {
        // 会员系统禁用时，返回统一限制
        if (!$this->isEnabled()) {
            return $this->getUnifiedLimits();
        }

        // 会员系统启用时，从数据库获取用户会员等级的限制
        return $this->getMembershipLimits($userId);
    }

    /**
     * 获取统一限制（会员系统禁用时使用）
     */
    public function getUnifiedLimits(): array
    {
        return config('membership.unified_limits', [
            'ai_queries_per_day' => 10,
            'max_training_plans' => 5,
            'dag_templates' => [
                'greeting', 'quick_consultation', 'exercise_optimization',
                'progress_analysis', 'safety_assessment', 'complete_training_plan',
                'nutrition_planning', 'comprehensive_fitness', 'rehabilitation_training',
                'posture_correction', 'plan_adjustment', 'fat_loss_program', 'strength_program'
            ],
            'dag_template_count' => 13,
            'complexity_limits' => [
                'simple' => 10,
                'medium' => 5,
                'complex' => 3
            ],
            'unlock_all_exercises' => true,
            'ai_recommendation' => true,
            'data_analysis' => false,
            'coach_service' => false,
        ]);
    }

    /**
     * 获取会员等级限制（会员系统启用时使用）
     */
    protected function getMembershipLimits(int $userId): array
    {
        // 从缓存获取用户会员信息
        $cacheKey = "user_membership_limits:{$userId}";
        
        return Cache::remember($cacheKey, 300, function () use ($userId) {
            $userMembership = \DB::table('user_memberships')
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->first();

            if (!$userMembership) {
                // 没有有效会员，返回免费版限制
                return $this->getFreeTierLimits();
            }

            $membership = \DB::table('memberships')
                ->where('id', $userMembership->membership_id)
                ->first();

            if (!$membership) {
                return $this->getFreeTierLimits();
            }

            $limits = json_decode($membership->limits, true) ?? [];
            
            return [
                'ai_queries_per_day' => $limits['ai_queries_per_day'] ?? 8,
                'max_training_plans' => $membership->max_training_plans ?? 3,
                'dag_templates' => $limits['dag_templates'] ?? [],
                'dag_template_count' => $limits['dag_template_count'] ?? 13,
                'complexity_limits' => $limits['complexity_limits'] ?? [
                    'simple' => 5,
                    'medium' => 2,
                    'complex' => 1
                ],
                'unlock_all_exercises' => (bool) $membership->unlock_all_exercises,
                'ai_recommendation' => (bool) $membership->ai_recommendation,
                'data_analysis' => (bool) $membership->data_analysis,
                'coach_service' => (bool) $membership->coach_service,
                'tier' => $membership->slug,
                'tier_name' => $membership->name,
            ];
        });
    }

    /**
     * 获取免费版限制
     */
    protected function getFreeTierLimits(): array
    {
        $membership = \DB::table('memberships')
            ->where('slug', 'free')
            ->first();

        if (!$membership) {
            // 数据库中没有免费版配置，使用默认值
            return [
                'ai_queries_per_day' => 8,
                'max_training_plans' => 3,
                'dag_templates' => [
                    'greeting', 'quick_consultation', 'exercise_optimization',
                    'progress_analysis', 'safety_assessment', 'complete_training_plan',
                    'nutrition_planning', 'comprehensive_fitness', 'rehabilitation_training',
                    'posture_correction', 'plan_adjustment', 'fat_loss_program', 'strength_program'
                ],
                'dag_template_count' => 13,
                'complexity_limits' => [
                    'simple' => 5,
                    'medium' => 2,
                    'complex' => 1
                ],
                'unlock_all_exercises' => true,
                'ai_recommendation' => true,
                'data_analysis' => false,
                'coach_service' => false,
                'tier' => 'free',
                'tier_name' => '免费版',
            ];
        }

        $limits = json_decode($membership->limits, true) ?? [];

        return [
            'ai_queries_per_day' => $limits['ai_queries_per_day'] ?? 8,
            'max_training_plans' => $membership->max_training_plans ?? 3,
            'dag_templates' => $limits['dag_templates'] ?? [],
            'dag_template_count' => $limits['dag_template_count'] ?? 13,
            'complexity_limits' => $limits['complexity_limits'] ?? [
                'simple' => 5,
                'medium' => 2,
                'complex' => 1
            ],
            'unlock_all_exercises' => (bool) $membership->unlock_all_exercises,
            'ai_recommendation' => (bool) $membership->ai_recommendation,
            'data_analysis' => (bool) $membership->data_analysis,
            'coach_service' => (bool) $membership->coach_service,
            'tier' => 'free',
            'tier_name' => '免费版',
        ];
    }

    /**
     * 获取前端配置（用于控制UI显示）
     */
    public function getFrontendConfig(): array
    {
        $enabled = $this->isEnabled();
        
        return [
            'membership_enabled' => $enabled,
            'show_membership_center' => $enabled,
            'show_purchase_button' => $enabled,
            'show_pricing_table' => $enabled,
            'show_donation_section' => !$enabled, // 会员系统禁用时显示打赏
            'unified_limits' => !$enabled ? $this->getUnifiedLimits() : null,
            'message' => !$enabled 
                ? '当前为免费体验模式，所有用户享受统一服务。觉得有帮助可以打赏支持开发者。' 
                : null,
        ];
    }

    /**
     * 清除用户会员缓存
     */
    public function clearUserCache(int $userId): void
    {
        Cache::forget("user_membership_limits:{$userId}");
        Cache::forget("user_membership:{$userId}");
    }
}
