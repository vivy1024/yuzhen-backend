<?php

namespace App\Modules\Food\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Food Resource
 * 
 * 食物列表资源（简化版）
 * 
 * @version 1.0.0
 * @date 2026-01-04
 */
class FoodResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'food_code' => $this->food_code,
            'name' => $this->name,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            
            // 基础营养素（每100g）
            'energy_kcal' => $this->energy_kcal,
            'protein' => $this->protein,
            'fat' => $this->fat,
            'carbohydrate' => $this->carbohydrate,
            'dietary_fiber' => $this->dietary_fiber,
        ];
    }
}
