<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 聊天话题创建/更新请求验证
 *
 * 用于 ChatTopicController::store() 和 update()
 * store: name 必填; update: name 可选
 */
class ChatTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name' => $isUpdate
                ? ['sometimes', 'required', 'string', 'max:100']
                : ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ];
    }
}
