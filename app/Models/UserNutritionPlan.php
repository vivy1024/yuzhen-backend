<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserNutritionPlan Model - 用户饮食计划
 *
 * @property int $id
 * @property int $plan_id
 * @property int|null $food_id
 * @property string $food_name
 * @property string $meal_type
 * @property float $portion_grams
 * @property int|null $day_of_week
 * @property string|null $notes
 * @property int $order_index
 */
class UserNutritionPlan extends Model
{
    protected $table = 'user_nutrition_plans';

    protected $fillable = [
        'plan_id',
        'food_id',
        'food_name',
        'meal_type',
        'portion_grams',
        'day_of_week',
        'notes',
        'order_index',
    ];

    protected $casts = [
        'plan_id' => 'integer',
        'food_id' => 'integer',
        'portion_grams' => 'float',
        'day_of_week' => 'integer',
        'order_index' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class, 'plan_id');
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
