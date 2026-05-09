<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AI 聊天请求验证 (v2.0 Skills-first Agent)
 *
 * 用于 AiProxyController::streamChat 和 AiProxyController::chat
 * 
 * v2.0 变更：
 * - 移除 strategy（不再暴露 dag/agent 切换）
 * - 移除 template_id（不再手动选模板）
 * - 移除 persona_id（由 Skill 内部决定）
 * - 新增 thread_id（对话线程持久化）
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
            'thread_id'   => ['sometimes', 'nullable', 'string', 'max:128'],
            'session_id'  => ['sometimes', 'nullable', 'string', 'max:128'],
            'topic_id'    => ['sometimes', 'nullable', 'integer', 'min:1'],
            'attachments'  => ['sometimes', 'nullable', 'array', 'max:5'],
            'attachments.*'=> ['string', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'query.max'        => '查询内容不能超过4000个字符',
            'user_id.required' => '用户ID不能为空',
            'thread_id.max'    => '线程ID不能超过128个字符',
        ];
    }
}
