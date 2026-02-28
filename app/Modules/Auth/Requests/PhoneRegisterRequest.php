<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 手机号注册请求验证
 *
 * 验证规则：
 * - nickname: 必填，2-50字符，唯一
 * - phone: 必填，中国手机号格式，唯一
 * - phone_code: 必填，6位数字验证码
 * - password: 必填，至少6位，需确认
 */
class PhoneRegisterRequest extends FormRequest
{
    /**
     * 授权检查
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 验证规则
     */
    public function rules(): array
    {
        return [
            'nickname' => 'required|string|min:2|max:50|unique:users,name',
            'phone' => [
                'required',
                'string',
                'regex:/^1[3-9]\d{9}$/',
                'unique:users,phone',
            ],
            'phone_code' => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ];
    }

    /**
     * 自定义错误消息
     */
    public function messages(): array
    {
        return [
            'nickname.required' => '请输入昵称',
            'nickname.min' => '昵称至少2个字符',
            'nickname.max' => '昵称最多50个字符',
            'nickname.unique' => '该昵称已被使用',

            'phone.required' => '请输入手机号',
            'phone.regex' => '请输入正确的手机号格式',
            'phone.unique' => '该手机号已被注册',

            'phone_code.required' => '请输入验证码',
            'phone_code.size' => '验证码必须是6位数字',

            'password.required' => '请输入密码',
            'password.min' => '密码至少6位',
            'password.confirmed' => '两次输入的密码不一致',
        ];
    }

    /**
     * 字段名称（用于验证消息）
     */
    public function attributes(): array
    {
        return [
            'nickname' => '昵称',
            'phone' => '手机号',
            'phone_code' => '验证码',
            'password' => '密码',
        ];
    }
}