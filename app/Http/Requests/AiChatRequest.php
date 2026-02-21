<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AI 聊天请求验证
 *
 * 用于 AiProxyController::streamChat 和 AiProxyController::chat
 * 防止超长输入被转发到 DAML-RAG 服务
 */
class AiChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'     => ['required', 'integer', 'min:1'],
            'query'       => ['required', 'string', 'min:1', 'max:4000'],
            'strategy'    => ['sometimes', 'string', 'in:dag,agent,template'],
            'session_id'  => ['sometimes', 'nullable', 'string', 'max:128'],
            'topic_id'    => ['sometimes', 'nullable', 'integer', 'min:1'],
            'tool_results'=> ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'query.max'      => '查询内容不能超过4000个字符',
            'strategy.in'    => '执行策略必须是 dag、agent 或 template 之一',
            'user_id.required' => '用户ID不能为空',
        ];
    }
}
