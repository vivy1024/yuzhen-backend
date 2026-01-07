<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 会员等级模型
 * 
 * @property int $id
 * @property string $name 等级名称
 * @property string $slug 唯一标识
 * @property string $tier 等级标识 (free/warmheart/energy)
 * @property float $price 价格（元）
 * @property int $duration_days 有效天数
 * @property int $max_training_plans 最大训练计划数量
 * @property bool $unlock_all_exercises 解锁所有动作
 * @property bool $ai_recommendation AI推荐功能
 * @property bool $data_analysis 数据分析功能
 * @property bool $coach_service 教练服务
 * @property string $description 等级描述
 * @property array $features 功能列表
 * @property array $limits 限制说明
 * @property int $sort_order 排序
 * @property bool $is_active 是否启用
 */
class Membership extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'tier',
        'price',
        'duration_days',
        'max_training_plans',
        'unlock_all_exercises',
        'ai_recommendation',
        'data_analysis',
        'coach_service',
        'description',
        'features',
        'limits',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'unlock_all_exercises' => 'boolean',
        'ai_recommendation' => 'boolean',
        'data_analysis' => 'boolean',
        'coach_service' => 'boolean',
        'features' => 'array',
        'limits' => 'array',
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
}



































