<?php

namespace App\Modules\Food\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Food Model
 * 
 * 食物数据模型
 * 数据来源：《中国食物成分表》
 * 
 * @version 1.0.0
 * @date 2026-01-04
 */
class Food extends Model
{
    protected $table = 'foods';

    protected $fillable = [
        // 基础信息
        'food_code',
        'name',
        'category',
        'subcategory',
        // 基础营养素
        'edible',
        'water',
        'energy_kcal',
        'energy_kj',
        'protein',
        'fat',
        'carbohydrate',
        'dietary_fiber',
        'cholesterol',
        'ash',
        // 维生素
        'vitamin_a',
        'carotene',
        'retinol',
        'thiamin',
        'riboflavin',
        'niacin',
        'vitamin_c',
        'vitamin_e_total',
        // 矿物质
        'calcium',
        'phosphorus',
        'potassium',
        'sodium',
        'magnesium',
        'iron',
        'zinc',
        'selenium',
        'copper',
        'manganese',
        // 扩展
        'remark',
        'gi_value',
        'price_level',
        'view_count',
    ];

    protected $casts = [
        'edible' => 'float',
        'water' => 'float',
        'energy_kcal' => 'float',
        'energy_kj' => 'float',
        'protein' => 'float',
        'fat' => 'float',
        'carbohydrate' => 'float',
        'dietary_fiber' => 'float',
        'cholesterol' => 'float',
        'ash' => 'float',
        'vitamin_a' => 'float',
        'carotene' => 'float',
        'retinol' => 'float',
        'thiamin' => 'float',
        'riboflavin' => 'float',
        'niacin' => 'float',
        'vitamin_c' => 'float',
        'vitamin_e_total' => 'float',
        'calcium' => 'float',
        'phosphorus' => 'float',
        'potassium' => 'float',
        'sodium' => 'float',
        'magnesium' => 'float',
        'iron' => 'float',
        'zinc' => 'float',
        'selenium' => 'float',
        'copper' => 'float',
        'manganese' => 'float',
        'gi_value' => 'integer',
        'view_count' => 'integer',
    ];

    /**
     * 按分类筛选
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * 按小类筛选
     */
    public function scopeBySubcategory(Builder $query, string $subcategory): Builder
    {
        return $query->where('subcategory', $subcategory);
    }

    /**
     * 搜索
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where('name', 'like', "%{$keyword}%");
    }

    /**
     * 高蛋白食物
     */
    public function scopeHighProtein(Builder $query, float $minProtein = 20): Builder
    {
        return $query->where('protein', '>=', $minProtein);
    }

    /**
     * 低热量食物
     */
    public function scopeLowCalorie(Builder $query, float $maxCalorie = 100): Builder
    {
        return $query->where('energy_kcal', '<=', $maxCalorie);
    }

    /**
     * 增加浏览次数
     */
    public function incrementViewCount(): bool
    {
        return $this->increment('view_count');
    }

    /**
     * 获取热量（别名）
     */
    public function getCaloriesAttribute(): ?float
    {
        return $this->energy_kcal;
    }

    /**
     * 获取碳水（别名）
     */
    public function getCarbsAttribute(): ?float
    {
        return $this->carbohydrate;
    }
}
