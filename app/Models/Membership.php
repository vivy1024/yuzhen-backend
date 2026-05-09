<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 会员等级模型 - 会员自动化控制系统
 * 
 * @property int $id
 * @property string $name 等级名称
 * @property string $slug 唯一标识
 * @property string $tier 等级标识 (free/warmheart/energy)
 * @property float $price 价格（元）
 * @property float $original_price 原价（元）
 * @property int $duration_days 有效天数
 * @property int $max_training_plans 最大训练计划数量
 * @property bool $unlock_all_exercises 解锁所有动作
 * @property bool $ai_recommendation AI推荐功能
 * @property bool $data_analysis 数据分析功能
 * @property bool $coach_service 教练服务
 * @property string $description 等级描述
 * @property array $features 功能列表
 * @property array $limits 限制配置（JSON）
 * @property bool $is_first_purchase 是否为首充优惠套餐
 * @property int $sort_order 排序
 * @property bool $is_active 是否启用
 * 
 * @version v1.1.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 9.2
 */
class Membership extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'tier',
        'price',
        'original_price',
        'duration_days',
        'max_training_plans',
        'unlock_all_exercises',
        'ai_recommendation',
        'data_analysis',
        'coach_service',
        'description',
        'features',
        'limits',
        'is_first_purchase',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'unlock_all_exercises' => 'boolean',
        'ai_recommendation' => 'boolean',
        'data_analysis' => 'boolean',
        'coach_service' => 'boolean',
        'features' => 'array',
        'limits' => 'array',
        'is_first_purchase' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * 用户关联（多对多）
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_memberships')
            ->withPivot(['order_id', 'started_at', 'expires_at', 'is_active'])
            ->withTimestamps();
    }

    /**
     * 用户会员记录（一对多）
     */
    public function userMemberships()
    {
        return $this->hasMany(UserMembership::class);
    }

    /**
     * 根据slug获取会员等级
     */
    public static function findBySlug(string $slug)
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * 根据tier获取会员等级
     */
    public static function findByTier(string $tier)
    {
        return static::where('tier', $tier)->first();
    }

    /**
     * 获取所有激活的会员等级
     */
    public static function getActive()
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * 获取首充优惠套餐
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getFirstPurchasePlans()
    {
        return static::where('is_active', true)
            ->where('is_first_purchase', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * 获取常规套餐（非首充优惠）
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getRegularPlans()
    {
        return static::where('is_active', true)
            ->where('is_first_purchase', false)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * 获取折扣百分比
     * 
     * @return int 折扣百分比（0-100）
     */
    public function getDiscountPercentage(): int
    {
        if ($this->original_price <= 0) {
            return 0;
        }
        
        $discount = (1 - $this->price / $this->original_price) * 100;
        return (int) round($discount);
    }

    /**
     * 获取limits配置中的每日DAG限制
     * 
     * @return int
     */
    public function getDailyDagLimit(): int
    {
        return $this->limits['daily_dag_limit'] ?? 5;
    }

    /**
     * 获取limits配置中的每日Agent限制
     * 
     * @return int
     */
    public function getDailyAgentLimit(): int
    {
        return $this->limits['daily_agent_limit'] ?? 0;
    }

    /**
     * 获取limits配置中的可用DAG模板
     * 
     * @return array
     */
    public function getAvailableDagTemplates(): array
    {
        return $this->limits['dag_templates'] ?? ['greeting', 'simple_exercise_query'];
    }

    /**
     * 检查是否可以使用Agent模式
     * 
     * @return bool
     */
    public function canUseAgent(): bool
    {
        return $this->limits['can_use_agent'] ?? false;
    }

    /**
     * 会员订单关联
     */
    public function orders()
    {
        return $this->hasMany(MembershipOrder::class);
    }
}



































