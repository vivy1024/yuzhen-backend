<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Login Request
 * 
 * 登录请求验证
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => 'required|string', // 用户名/邮箱/手机号
            'password' => 'required|string|min:6',
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => '请输入用户名/邮箱/手机号',
            'password.required' => '请输入密码',
            'password.min' => '密码至少6位',
        ];
    }
}

