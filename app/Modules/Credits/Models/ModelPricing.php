<?php

namespace App\Modules\Credits\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ModelPricing - 模型定价模型
 *
 * 对应 model_pricing 表
 *
 * @property int $id
 * @property string $model_name
 * @property string $input_price_per_ktoken 输入价格 credits/千token
 * @property string $output_price_per_ktoken 输出价格 credits/千token
 * @property string $real_input_cost_usd 实际输入成本 USD/千token
 * @property string $real_output_cost_usd 实际输出成本 USD/千token
 * @property string $cost_tier free|low|free_quota|baseline
 * @property bool $enabled
 * @property \Carbon\Carbon $updated_at
 */
class ModelPricing extends Model
{
    protected $table = 'model_pricing';

    public $timestamps = false;

    protected $fillable = [
        'model_name',
        'input_price_per_ktoken',
        'output_price_per_ktoken',
        'real_input_cost_usd',
        'real_output_cost_usd',
        'cost_tier',
        'enabled',
    ];

    protected $casts = [
        'input_price_per_ktoken' => 'decimal:6',
        'output_price_per_ktoken' => 'decimal:6',
        'real_input_cost_usd' => 'decimal:8',
        'real_output_cost_usd' => 'decimal:8',
        'enabled' => 'boolean',
    ];

    // 层级常量
    const TIER_FREE = 'free';
    const TIER_LOW = 'low';
    const TIER_FREE_QUOTA = 'free_quota';
    const TIER_BASELINE = 'baseline';

    /**
     * Scope: 仅启用的模型
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * 按模型名称查找
     */
    public static function findByModel(string $modelName): ?self
    {
        return static::where('model_name', $modelName)->first();
    }
}
