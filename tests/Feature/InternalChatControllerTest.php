<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Infrastructure\Http\Middleware\InternalApiAuth;

/**
 * Internal Chat API Feature 测试
 *
 * 测试 DAML-RAG 调用的内部聊天 API
 *
 * @version v1.0.0
 * @date 2026-02-22
 */
class InternalChatControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([InternalApiAuth::class]);
    }

    protected function createUser(): User
    {
        return User::create([
            'name' => 'Chat Test User',
            'email' => 'chat-test@test.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
    }

    /**
     * 测试保存对话记录
     */
    public function test_save_chat_session()
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/internal/chat/save-session', [
            'session_id' => 'sess_test_001',
            'user_id' => $user->id,
            'user_query' => '推荐几个胸肌训练动作',
            'llm_response' => '推荐以下胸肌训练动作：1. 卧推 2. 飞鸟 3. 俯卧撑',
            'model_used' => 'claude-haiku-4.5',
            'tools_used' => ['exercise_search', 'knowledge_base'],
        ]);

        $response->assertStatus(200)
            ->assertJson(['code' => 200])
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
            ]);
    }

    /**
     * 测试保存对话记录 - 参数验证失败
     */
    public function test_save_chat_session_validation_fails()
    {
        $response = $this->postJson('/api/internal/chat/save-session', [
            'session_id' => 'sess_test_002',
            // 缺少 user_query, llm_response, model_used
        ]);

        $response->assertStatus(422);
    }

    /**
     * 测试保存话题
     */
    public function test_save_topic()
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/internal/chat/save-topic', [
            'user_id' => (string) $user->id,
            'topic_id' => 'topic_test_001',
            'name' => '胸肌训练讨论',
        ]);

        $response->assertStatus(200)
            ->assertJson(['code' => 200]);
    }

    /**
     * 测试保存消息
     */
    public function test_save_message()
    {
        $user = $this->createUser();

        // 先通过 API 创建话题，获取真实 topic ID
        $topicResponse = $this->postJson('/api/internal/chat/save-topic', [
            'user_id' => (string) $user->id,
            'topic_id' => 'topic_msg_001',
            'name' => '测试话题',
        ]);
        $topicId = $topicResponse->json('data.topic_id');

        $response = $this->postJson('/api/internal/chat/save-message', [
            'topic_id' => (string) $topicId,
            'user_id' => (string) $user->id,
            'role' => 'user',
            'content' => '我想练胸肌',
        ]);

        $response->assertStatus(200)
            ->assertJson(['code' => 200]);
    }

    /**
     * 测试获取用户对话历史
     */
    public function test_get_user_chat_history()
    {
        $user = $this->createUser();

        $response = $this->getJson("/api/internal/chat/user/{$user->id}/history");

        $response->assertStatus(200)
            ->assertJson(['code' => 200])
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
            ]);
    }

    /**
     * 测试获取用户会话计数
     */
    public function test_get_session_count()
    {
        $user = $this->createUser();

        $response = $this->getJson("/api/internal/chat/session-count/{$user->id}");

        $response->assertStatus(200)
            ->assertJson(['code' => 200]);
    }
}
