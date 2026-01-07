<?php

namespace App\Modules\Membership\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_id' => 'required|integer|exists:memberships,id',
        ];
    }

    public function messages(): array
    {
        return [
            'membership_id.required' => '请选择会员等级',
            'membership_id.exists' => '会员等级不存在',
        ];
    }
}

