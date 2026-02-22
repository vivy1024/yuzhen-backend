<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    /**
     * 注册推送订阅
     * POST /api/push/subscribe
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'subscription' => 'required|array',
            'subscription.endpoint' => 'required|url',
            'subscription.keys.p256dh' => 'required|string',
            'subscription.keys.auth' => 'required|string',
            'reminder_time' => 'nullable|date_format:H:i',
        ]);

        $sub = $request->input('subscription');
        $userId = $request->user()->id;

        // upsert by endpoint
        PushSubscription::updateOrCreate(
            ['user_id' => $userId, 'endpoint' => $sub['endpoint']],
            [
                'p256dh' => $sub['keys']['p256dh'],
                'auth' => $sub['keys']['auth'],
                'reminder_time' => $request->input('reminder_time', '09:00'),
                'is_active' => true,
            ]
        );

        return response()->json([
            'code' => 200,
            'msg' => '推送订阅成功',
            'data' => null,
        ]);
    }

    /**
     * 取消推送订阅
     * POST /api/push/unsubscribe
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint', $request->input('endpoint'))
            ->update(['is_active' => false]);

        return response()->json([
            'code' => 200,
            'msg' => '已取消推送',
            'data' => null,
        ]);
    }

    /**
     * 更新提醒时间
     * PUT /api/push/reminder-time
     */
    public function updateReminderTime(Request $request): JsonResponse
    {
        $request->validate([
            'reminder_time' => 'required|date_format:H:i',
        ]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->update(['reminder_time' => $request->input('reminder_time')]);

        return response()->json([
            'code' => 200,
            'msg' => '提醒时间已更新',
            'data' => null,
        ]);
    }
}
