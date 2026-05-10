<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 创建/更新训练计划请求验证
 */
class UserPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'goal' => 'nullable|in:hypertrophy,fat_loss,strength,endurance,body_shaping,general_fitness,functional,rehabilitation,athletic_performance',
            'difficulty' => 'nullable|in:novice,beginner,intermediate,advanced',
            'duration_weeks' => 'required|integer|min:1|max:52',
            'workouts_per_week' => 'required|integer|min:1|max:7',
            'exercises' => 'required|array|min:1',
            'exercises.*.exercise_id' => 'nullable|integer|exists:exercises,id',
            'exercises.*.exercise_name' => 'required|string|max:100',
            'exercises.*.day_of_week' => 'nullable|integer|min:1|max:7',
            'exercises.*.sets' => 'required|integer|min:1|max:20',
            'exercises.*.reps' => 'required|string|max:20',
            'exercises.*.weight' => 'nullable|string|max:50',
            'exercises.*.rest_time' => 'nullable|string|max:20',
            'exercises.*.notes' => 'nullable|string|max:200',
            'exercises.*.order_index' => 'nullable|integer|min:0',
            'nutrition' => 'nullable|array',
            'nutrition.*.food_id' => 'nullable|integer|exists:foods,id',
            'nutrition.*.food_name' => 'required|string|max:100',
            'nutrition.*.meal_type' => 'required|in:breakfast,lunch,dinner,snack',
            'nutrition.*.portion_grams' => 'nullable|numeric|min:1|max:5000',
            'nutrition.*.day_of_week' => 'nullable|integer|min:1|max:7',
            'nutrition.*.notes' => 'nullable|string|max:200',
        ];

        // PUT 请求时 exercises 非必填
        if ($this->isMethod('PUT')) {
            $rules['name'] = 'sometimes|required|string|max:100';
            $rules['duration_weeks'] = 'sometimes|required|integer|min:1|max:52';
            $rules['workouts_per_week'] = 'sometimes|required|integer|min:1|max:7';
            $rules['exercises'] = 'sometimes|array|min:1';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => '计划名称不能为空',
            'exercises.required' => '至少添加一个训练动作',
            'exercises.min' => '至少添加一个训练动作',
            'exercises.*.exercise_name.required' => '动作名称不能为空',
            'exercises.*.sets.required' => '组数不能为空',
            'exercises.*.reps.required' => '次数不能为空',
        ];
    }
}
