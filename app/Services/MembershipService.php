<?php

namespace App\Services;

use App\Models\User;
use App\Models\Membership;
use App\Models\UserMembership;
use Illuminate\Support\Facades\Log;

/**
 * MembershipService - 会员管理服务
 * 
 * 负责会员等级、权限和限制的管理
 * 
 * 核心功能：
 * - 获取当前会员状态
 * - 获取用户权限
 * - 获取有效限制（考虑Feature Flag）
 * 
 * Feature Flag行为：
 * - MEMBERSHIP_SYSTEM_ENABLED=false: 所有用户获得ENERGY级别权限，但仍受每日限制
 * - MEMBERSHIP_SYSTEM_ENABLED=true: 根据实际会员等级分配权限
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 1.1-1.6, 5.1-5.6
 */
class MembershipService
{
    /**
     * 会员等级常量
     */
    const TIER_FREE = 'free';
    const TIER_WARMHEART = 'warmheart';
    const TIER_ENERGY = 'energy';

    /**
     * 各等级的默认限制配置
     */
    const TIER_LIMITS = [
        self::TIER_FREE => [
            'daily_dag_limit' => 5,
            'daily_agent_limit' => 0,
            'can_use_agent' => false,
            'dag_templates' => ['greeting', 'simple_exercise_query'],
            'training_plan_limit' => 3,
            'advanced_analysis' => false,
        ],
        self::TIER_WARMHEART => [
            'daily_dag_limit' => 10,
            'daily_agent_limit' => 0,
            'can_use_agent' => false,
            'dag_templates' => [
                'greeting', 'simple_exercise_query', 'exercise_detail',
                'muscle_exercise_query', 'equipment_exercise_query',
                'exercise_comparison', 'workout_plan_simple',
                'nutrition_query', 'food_search', 'meal_plan',
                'fitness_qa', 'progress_tracking', 'goal_setting'
            ],
            'training_plan_limit' => 10,
            'advanced_analysis' => false,
        ],
        self::TIER_ENERGY => [
            'daily_dag_limit' => 999999,
            'daily_agent_limit' => 999999,
            'can_use_agent' => true,
            'dag_templates' => 'all',
            'training_plan_limit' => 999999,
            'advanced_analysis' => true,
        ],
    ];

    /**
     * 开发测试阶段的默认限制（Feature Flag关闭时）
     */
    const DEV_LIMITS = [
        'daily_dag_limit' => 10,
        'daily_agent_limit' => 3,
        'can_use_agent' => true,
        'dag_templates' => 'all',
        'training_plan_limit' => 999999,
        'advanced_analysis' => true,
    ];

    /**
     * 检查会员系统是否启用
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return config('membership.enabled', false);
    }

    /**
     * 获取会员系统配置
     * 
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'tiers' => [
                self::TIER_FREE => [
                    'name' => '免费用户',
                    'description' => '基础功能体验',
                ],
                self::TIER_WARMHEART => [
                    'name' => '暖心会员',
                    'description' => '解锁全部DAG模板',
                ],
                self::TIER_ENERGY => [
                    'name' => '能量会员',
                    'description' => '无限制使用所有功能',
                ],
            ],
        ];
    }

    /**
     * 获取用户当前会员状态
     * 
     * @param int $userId 用户ID
     * @return array|null 会员状态信息，无会员返回null
     * 
     * 返回格式：
     * [
     *     'membership_id' => int,
     *     'tier' => string,
     *     'name' => string,
     *     'started_at' => string,
     *     'expires_at' => string|null,
     *     'remaining_days' => int|null,
     *     'auto_renew' => bool,
     *     'status' => string,
     *     'is_valid' => bool,
     * ]
     */
    public function getCurrentMembership(int $userId): ?array
    {
        $user = User::find($userId);
        
        if (!$user) {
            return null;
        }
        
        // 获取用户当前有效的会员记录
        $userMembership = UserMembership::getActiveByUserId($userId);
        
        if (!$userMembership || !$userMembership->isValid()) {
            return null;
        }
        
        $membership = $userMembership->membership;
        
        return [
            'membership_id' => $membership->id,
            'tier' => $membership->tier,
            'name' => $membership->name,
            'started_at' => $userMembership->started_at->toDateTimeString(),
            'expires_at' => $userMembership->expires_at?->toDateTimeString(),
            'remaining_days' => $userMembership->remainingDays(),
            'auto_renew' => $userMembership->auto_renew,
            'status' => $userMembership->status,
            'is_valid' => true,
        ];
    }

    /**
     * 获取用户权限
     * 
     * @param int $userId 用户ID
     * @return array 权限信息
     * 
     * 返回格式：
     * [
     *     'tier' => string,
     *     'can_use_agent' => bool,
     *     'dag_templates' => array|string,
     *     'daily_dag_limit' => int,
     *     'daily_agent_limit' => int,
     *     'training_plan_limit' => int,
     *     'advanced_analysis' => bool,
     * ]
     */
    public function getUserPermissions(int $userId): array
    {
        // 如果会员系统未启用，返回开发测试阶段权限
        if (!$this->isEnabled()) {
            return array_merge(
                ['tier' => self::TIER_ENERGY],
                self::DEV_LIMITS
            );
        }
        
        // 获取用户实际会员等级
        $tier = $this->getUserTier($userId);
        
        // 返回对应等级的权限
        return array_merge(
            ['tier' => $tier],
            self::TIER_LIMITS[$tier] ?? self::TIER_LIMITS[self::TIER_FREE]
        );
    }

    /**
     * 获取用户有效限制（考虑Feature Flag）
     * 
     * @param int $userId 用户ID
     * @return array 限制配置
     * 
     * 返回格式：
     * [
     *     'daily_dag_limit' => int,
     *     'daily_agent_limit' => int,
     *     'can_use_agent' => bool,
     *     'dag_templates' => array|string,
     *     'training_plan_limit' => int,
     *     'advanced_analysis' => bool,
     * ]
     */
    public function getEffectiveLimits(int $userId): array
    {
        // 如果会员系统未启用，使用开发测试阶段限制
        if (!$this->isEnabled()) {
            return self::DEV_LIMITS;
        }
        
        // 获取用户实际会员等级
        $tier = $this->getUserTier($userId);
        
        // 检查是否有有效的会员订阅
        $userMembership = UserMembership::getActiveByUserId($userId);
        
        if ($userMembership && $userMembership->isValid()) {
            // 使用会员套餐中定义的limits
            $membership = $userMembership->membership;
            
            if ($membership && !empty($membership->limits)) {
                return array_merge(
                    self::TIER_LIMITS[$tier] ?? self::TIER_LIMITS[self::TIER_FREE],
                    $membership->limits
                );
            }
        }
        
        // 返回等级默认限制
        return self::TIER_LIMITS[$tier] ?? self::TIER_LIMITS[self::TIER_FREE];
    }

    /**
     * 获取用户会员等级
     * 
     * @param int $userId 用户ID
     * @return string 会员等级
     */
    public function getUserTier(int $userId): string
    {
        $user = User::find($userId);
        
        if (!$user) {
            return self::TIER_FREE;
        }
        
        // 优先使用用户表中的membership_tier字段
        if (!empty($user->membership_tier)) {
            return $user->membership_tier;
        }
        
        // 检查是否有有效的会员订阅
        $userMembership = UserMembership::getActiveByUserId($userId);
        
        if ($userMembership && $userMembership->isValid()) {
            return $userMembership->membership->tier ?? self::TIER_FREE;
        }
        
        return self::TIER_FREE;
    }

    /**
     * 检查用户是否可以使用Agent模式
     * 
     * @param int $userId 用户ID
     * @return bool
     */
    public function canUseAgent(int $userId): bool
    {
        $permissions = $this->getUserPermissions($userId);
        return $permissions['can_use_agent'] ?? false;
    }

    /**
     * 检查用户是否可以使用指定的DAG模板
     * 
     * @param int $userId 用户ID
     * @param string $templateName 模板名称
     * @return bool
     */
    public function canUseDagTemplate(int $userId, string $templateName): bool
    {
        $permissions = $this->getUserPermissions($userId);
        $templates = $permissions['dag_templates'] ?? [];
        
        // 如果是'all'，允许所有模板
        if ($templates === 'all') {
            return true;
        }
        
        // 检查模板是否在允许列表中
        return in_array($templateName, $templates);
    }

    /**
     * 获取用户可用的DAG模板列表
     * 
     * @param int $userId 用户ID
     * @return array|string 模板列表或'all'
     */
    public function getAvailableDagTemplates(int $userId)
    {
        $permissions = $this->getUserPermissions($userId);
        return $permissions['dag_templates'] ?? ['greeting', 'simple_exercise_query'];
    }

    /**
     * 检查用户是否为免费用户
     * 
     * @param int $userId 用户ID
     * @return bool
     */
    public function isFreeUser(int $userId): bool
    {
        return $this->getUserTier($userId) === self::TIER_FREE;
    }

    /**
     * 检查用户是否为暖心会员
     * 
     * @param int $userId 用户ID
     * @return bool
     */
    public function isWarmheartMember(int $userId): bool
    {
        return $this->getUserTier($userId) === self::TIER_WARMHEART;
    }

    /**
     * 检查用户是否为能量会员
     * 
     * @param int $userId 用户ID
     * @return bool
     */
    public function isEnergyMember(int $userId): bool
    {
        return $this->getUserTier($userId) === self::TIER_ENERGY;
    }

    /**
     * 更新用户会员等级
     * 
     * @param int $userId 用户ID
     * @param string $tier 新的会员等级
     * @return bool
     */
    public function updateUserTier(int $userId, string $tier): bool
    {
        if (!in_array($tier, [self::TIER_FREE, self::TIER_WARMHEART, self::TIER_ENERGY])) {
            Log::warning('尝试设置无效的会员等级', [
                'user_id' => $userId,
                'tier' => $tier,
            ]);
            return false;
        }
        
        $user = User::find($userId);
        
        if (!$user) {
            return false;
        }
        
        $oldTier = $user->membership_tier;
        $user->membership_tier = $tier;
        $result = $user->save();
        
        if ($result) {
            Log::info('用户会员等级已更新', [
                'user_id' => $userId,
                'old_tier' => $oldTier,
                'new_tier' => $tier,
            ]);
        }
        
        return $result;
    }

    /**
     * 获取所有可用的会员套餐
     * 
     * @param bool $includeFirstPurchase 是否包含首充优惠套餐
     * @return array
     */
    public function getAvailablePlans(bool $includeFirstPurchase = true): array
    {
        $plans = Membership::getRegularPlans();
        
        if ($includeFirstPurchase) {
            $firstPurchasePlans = Membership::getFirstPurchasePlans();
            $plans = $plans->merge($firstPurchasePlans);
        }
        
        return $plans->map(function ($plan) {
            return [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
                'tier' => $plan->tier,
                'price' => $plan->price,
                'original_price' => $plan->original_price,
                'discount_percentage' => $plan->getDiscountPercentage(),
                'duration_days' => $plan->duration_days,
                'is_first_purchase' => $plan->is_first_purchase,
                'limits' => $plan->limits,
                'features' => $plan->features,
            ];
        })->toArray();
    }

    /**
     * 获取用户可购买的套餐（考虑首充优惠使用情况）
     * 
     * @param int $userId 用户ID
     * @return array
     */
    public function getAvailablePlansForUser(int $userId): array
    {
        $user = User::find($userId);
        
        if (!$user) {
            return $this->getAvailablePlans(false);
        }
        
        // 如果用户已使用首充优惠，不显示首充套餐
        $includeFirstPurchase = !$user->first_purchase_used;
        
        return $this->getAvailablePlans($includeFirstPurchase);
    }

    /**
     * 获取会员等级的权限描述
     * 
     * @param string $tier 会员等级
     * @return array
     */
    public function getTierDescription(string $tier): array
    {
        $limits = self::TIER_LIMITS[$tier] ?? self::TIER_LIMITS[self::TIER_FREE];
        
        $descriptions = [
            self::TIER_FREE => [
                'name' => '免费用户',
                'features' => [
                    '每日5次DAG查询',
                    '2个基础DAG模板',
                    '3个训练计划',
                ],
                'restrictions' => [
                    '无法使用Agent模式',
                    '无法使用高级分析',
                ],
            ],
            self::TIER_WARMHEART => [
                'name' => '暖心会员',
                'features' => [
                    '每日10次DAG查询',
                    '全部13个DAG模板',
                    '10个训练计划',
                ],
                'restrictions' => [
                    '无法使用Agent模式',
                    '无法使用高级分析',
                ],
            ],
            self::TIER_ENERGY => [
                'name' => '能量会员',
                'features' => [
                    '无限DAG查询',
                    '无限Agent查询',
                    '全部DAG模板',
                    '无限训练计划',
                    '高级数据分析',
                ],
                'restrictions' => [],
            ],
        ];
        
        return $descriptions[$tier] ?? $descriptions[self::TIER_FREE];
    }
}
