<?php

namespace App\Modules\Membership\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Membership Model
 * 
 * 会员等级模型
 */
class Membership extends Model
{
    use HasFactory;

    protected $table = 'memberships';

    protected $fillable = [
        'name',
        'name_zh',
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
        'duration_days' => 'integer',
        'max_training_plans' => 'integer',
        'unlock_all_exercises' => 'boolean',
        'ai_recommendation' => 'boolean',
        'data_analysis' => 'boolean',
        'coach_service' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'features' => 'array',
        'limits' => 'array',
    ];

    /**
     * 关联：用户会员
     */
    public function userMemberships()
    {
        return $this->hasMany(UserMembership::class);
    }

    /**
     * 是否免费
     */
    public function isFree(): bool
    {
        return $this->slug === 'free' || $this->price == 0;
    }

    /**
     * 获取人类可读的价格
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->isFree()) {
            return '免费';
        }
        return '¥' . number_format($this->price, 2);
    }

    /**
     * 获取有效期描述
     */
    public function getDurationTextAttribute(): string
    {
        if ($this->duration_days >= 365) {
            return round($this->duration_days / 365) . '年';
        } elseif ($this->duration_days >= 30) {
            return round($this->duration_days / 30) . '个月';
        } else {
            return $this->duration_days . '天';
        }
    }

    /**
     * 获取权益列表
     */
    public function getFeatures(): array
    {
        $features = [];

        if ($this->unlock_all_exercises) {
            $features[] = '解锁所有动作库';
        }

        if ($this->max_training_plans > 10) {
            $features[] = '无限训练计划';
        } else {
            $features[] = "最多{$this->max_training_plans}个训练计划";
        }

        if ($this->ai_recommendation) {
            $features[] = 'AI智能推荐';
        }

        if ($this->data_analysis) {
            $features[] = '数据分析报表';
        }

        if ($this->coach_service) {
            $features[] = '专属教练服务';
        }

        return $features;
    }

    /**
     * 作用域：启用的会员等级
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 作用域：按排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }
}

