<?php

namespace App\Modules\Exercise\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filter Exercise Request
 * 
 * 动作筛选请求验证
 * 
 * 前端发送逗号分隔的多选值（如 difficulty=Beginner,Intermediate）
 * Repository 层负责 explode 拆分，这里只做基本字符串验证
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
            // 器械：支持逗号分隔多选（如 "杠铃,哑铃"）
            'equipment' => 'nullable|string|max:500',
            // 难度：支持逗号分隔多选（如 "Beginner,Intermediate"）
            'difficulty' => 'nullable|string|max:200',
            // 力量类型：前端发 force（非 force_type）
            'force' => 'nullable|string|max:100',
            // 机制类型：前端发 mechanic（非 mechanic_type）
            'mechanic' => 'nullable|string|max:100',
            // 握法
            'grip' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:200',
            'query' => 'nullable|string|max:200',
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
            'per_page.max' => '每页最多显示100条记录',
        ];
    }
}
