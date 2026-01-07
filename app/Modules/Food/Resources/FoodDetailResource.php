<?php

namespace App\Modules\Food\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Food Detail Resource
 * 
 * 食物详情资源（完整版）
 * 
 * @version 1.0.0
 * @date 2026-01-04
 */
class FoodDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'food_code' => $this->food_code,
            'name' => $this->name,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            
            // 基础信息
            'edible' => $this->edible,
            'water' => $this->water,
            
            // 能量
            'energy_kcal' => $this->energy_kcal,
            'energy_kj' => $this->energy_kj,
            
            // 宏量营养素
            'macros' => [
                'protein' => $this->protein,
                'fat' => $this->fat,
                'carbohydrate' => $this->carbohydrate,
                'dietary_fiber' => $this->dietary_fiber,
                'cholesterol' => $this->cholesterol,
            ],
            
            // 维生素
            'vitamins' => [
                'vitamin_a' => $this->vitamin_a,
                'carotene' => $this->carotene,
                'retinol' => $this->retinol,
                'thiamin' => $this->thiamin,
                'riboflavin' => $this->riboflavin,
                'niacin' => $this->niacin,
                'vitamin_c' => $this->vitamin_c,
                'vitamin_e_total' => $this->vitamin_e_total,
            ],
            
            // 矿物质
            'minerals' => [
                'calcium' => $this->calcium,
                'phosphorus' => $this->phosphorus,
                'potassium' => $this->potassium,
                'sodium' => $this->sodium,
                'magnesium' => $this->magnesium,
                'iron' => $this->iron,
                'zinc' => $this->zinc,
                'selenium' => $this->selenium,
                'copper' => $this->copper,
                'manganese' => $this->manganese,
            ],
            
            // 扩展信息
            'remark' => $this->remark,
            'gi_value' => $this->gi_value,
            'price_level' => $this->price_level,
            
            // 统计
            'view_count' => $this->view_count,
            
            // 数据来源
            'source' => '《中国食物成分表》',
        ];
    }
}
