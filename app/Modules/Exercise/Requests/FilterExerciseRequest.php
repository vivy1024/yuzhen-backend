<?php

namespace App\Modules\Exercise\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filter Exercise Request
 * 
 * 动作筛选请求验证
 */
class FilterExerciseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'muscle' => 'nullable|string|max:100',
            'equipment' => 'nullable|string|max:100',
            // ✅ 修复：与数据库实际值匹配（首字母大写）
            'difficulty' => 'nullable|string|in:Beginner,Novice,Intermediate,Advanced',
            'force_type' => 'nullable|string|in:Push,Pull,Static,Hold',
            'mechanic_type' => 'nullable|string|in:Compound,Isolation',
            'search' => 'nullable|string|max:200',
            'query' => 'nullable|string|max:200', // ✅ 添加query参数支持
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'difficulty.in' => '难度必须是：Beginner, Novice, Intermediate, Advanced 之一',
            'force_type.in' => '力量类型必须是：Push, Pull, Static, Hold 之一',
            'mechanic_type.in' => '机制类型必须是：Compound, Isolation 之一',
            'per_page.max' => '每页最多显示100条记录',
        ];
    }
}

