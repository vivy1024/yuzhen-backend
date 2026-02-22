<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PushSubscription;
use App\Modules\Auth\Middleware\JwtAuthenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([JwtAuthenticate::class]);
        $this->user = User::factory()->create();
    }

    public function test_subscribe_creates_push_subscription(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/push/subscribe', [
                'subscription' => [
                    'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
                    'keys' => [
                        'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8p8REfWRk',
                        'auth' => 'tBHItJI5svbpC7htDNae2A',
                    ],
                ],
                'reminder_time' => '08:30',
            ]);

        $response->assertOk()
            ->assertJson(['code' => 200, 'msg' => '推送订阅成功']);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->user->id,
            'reminder_time' => '08:30',
            'is_active' => true,
        ]);
    }

    public function test_subscribe_upserts_existing_endpoint(): void
    {
        PushSubscription::create([
            'user_id' => $this->user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
            'p256dh' => 'old-key',
            'auth' => 'old-auth',
            'reminder_time' => '09:00',
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/push/subscribe', [
                'subscription' => [
                    'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
                    'keys' => [
                        'p256dh' => 'new-key',
                        'auth' => 'new-auth',
                    ],
                ],
                'reminder_time' => '07:00',
            ]);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'p256dh' => 'new-key',
            'reminder_time' => '07:00',
        ]);
    }

    public function test_unsubscribe_deactivates_subscription(): void
    {
        PushSubscription::create([
            'user_id' => $this->user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
            'p256dh' => 'key',
            'auth' => 'auth',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/push/unsubscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
            ]);

        $response->assertOk()
            ->assertJson(['code' => 200]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->user->id,
            'is_active' => false,
        ]);
    }

    public function test_update_reminder_time(): void
    {
        PushSubscription::create([
            'user_id' => $this->user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
            'p256dh' => 'key',
            'auth' => 'auth',
            'is_active' => true,
            'reminder_time' => '09:00',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/push/reminder-time', [
                'reminder_time' => '20:00',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->user->id,
            'reminder_time' => '20:00',
        ]);
    }

    public function test_subscribe_requires_auth(): void
    {
        // 重新启用中间件来测试认证
        $this->withMiddleware();

        $response = $this->postJson('/api/push/subscribe', [
            'subscription' => [
                'endpoint' => 'https://example.com',
                'keys' => ['p256dh' => 'x', 'auth' => 'y'],
            ],
        ]);

        // JWT中间件拒绝未认证请求（401或500取决于异常处理）
        $this->assertNotEquals(200, $response->status());
        $this->assertDatabaseCount('push_subscriptions', 0);
    }
}
