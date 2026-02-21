<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 聊天消息批量同步请求验证
 *
 * 用于 ChatTopicController::syncMessages()
 */
class SyncMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'messages' => ['required', 'array'],
            'messages.*.role' => ['required', 'in:user,assistant,system'],
            'messages.*.content' => ['required', 'string'],
            'messages.*.client_id' => ['required', 'string', 'max:64'],
            'messages.*.timestamp' => ['nullable', 'integer'],
            'messages.*.metadata' => ['nullable', 'array'],
        ];
    }
}
