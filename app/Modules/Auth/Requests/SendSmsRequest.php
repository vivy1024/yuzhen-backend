<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 发送短信验证码请求验证
 */
class SendSmsRequest extends FormRequest
{
    /**
     * 确定用户是否有权限发出此请求
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 获取应用于请求的验证规则
     */
    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'regex:/^1[3-9]\d{9}$/',
            ],
        ];
    }

    /**
     * 获取验证错误的自定义消息
     */
    public function messages(): array
    {
        return [
            'phone.required' => '请输入手机号',
            'phone.string' => '手机号格式不正确',
            'phone.regex' => '请输入正确的11位手机号',
        ];
    }

    /**
     * 获取验证属性的自定义名称
     */
    public function attributes(): array
    {
        return [
            'phone' => '手机号',
        ];
    }
}
