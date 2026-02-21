<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Complaint;

/**
 * 投诉列表查询请求验证
 *
 * 用于 ComplaintController::index，验证 status 枚举值
 */
class ComplaintIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validStatuses = array_keys(Complaint::getStatuses());

        return [
            'status'   => ['sometimes', 'nullable', 'string', 'in:' . implode(',', $validStatuses)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'   => '投诉状态值无效',
            'per_page.max' => '每页最多返回100条记录',
        ];
    }
}
