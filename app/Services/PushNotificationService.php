<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotificationService
{
    private WebPush $webPush;

    public function __construct()
    {
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => config('app.url'),
                'publicKey' => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);
    }

    /**
     * 向指定用户发送推送
     */
    public function sendToUser(int $userId, string $title, string $body, string $url = '/'): int
    {
        $subscriptions = PushSubscription::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $sent = 0;
        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->p256dh,
                'authToken' => $sub->auth,
            ]);

            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'url' => $url,
            ]);

            $this->webPush->queueNotification($subscription, $payload);
            $sent++;
        }

        // flush — 发送所有排队的通知
        foreach ($this->webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                // 订阅过期，标记为非活跃
                PushSubscription::where('endpoint', $report->getEndpoint())
                    ->update(['is_active' => false]);
            }
        }

        return $sent;
    }

    /**
     * 发送训练提醒（按提醒时间匹配）
     */
    public function sendTrainingReminders(string $currentTime): int
    {
        $subscriptions = PushSubscription::where('is_active', true)
            ->where('reminder_time', $currentTime)
            ->with('user')
            ->get();

        $total = 0;
        foreach ($subscriptions as $sub) {
            $userName = $sub->user->name ?? '健身达人';
            $total += $this->sendToUser(
                $sub->user_id,
                '🏋️ 训练时间到！',
                "{$userName}，今天的训练计划在等你，加油！",
                '/training'
            );
        }

        return $total;
    }
}
