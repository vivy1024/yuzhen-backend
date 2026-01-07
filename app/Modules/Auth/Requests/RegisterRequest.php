<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Register Request
 * 
 * 注册请求验证
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nickname' => 'required|string|min:2|max:50|unique:users,name',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|regex:/^1[3-9]\d{9}$/',
            'gender' => 'nullable|string|in:male,female',
            'age' => 'nullable|integer|min:13|max:120',
        ];
    }

    public function messages(): array
    {
        return [
            'nickname.required' => '请输入昵称',
            'nickname.min' => '昵称至少2个字符',
            'nickname.unique' => '昵称已存在',
            'email.required' => '请输入邮箱',
            'email.email' => '邮箱格式不正确',
            'email.unique' => '邮箱已被注册',
            'password.required' => '请输入密码',
            'password.min' => '密码至少6位',
            'password.confirmed' => '两次密码不一致',
            'phone.regex' => '手机号格式不正确',
        ];
    }
}

