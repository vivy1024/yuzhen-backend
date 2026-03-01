<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserConsentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * 协议同意记录控制器
 *
 * 记录用户同意隐私政策/用户协议的行为，用于合规举证。
 */
class ConsentController extends Controller
{
    /**
     * 记录用户同意协议
     *
     * POST /api/consent/record
     * Body: { consent_type: "terms"|"privacy", consent_version: "2026-03-01" }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consent_type' => 'required|in:terms,privacy',
            'consent_version' => 'required|string|max:20',
        ]);

        $record = UserConsentRecord::create([
            'user_id' => $request->user()->id,
            'consent_type' => $validated['consent_type'],
            'consent_version' => $validated['consent_version'],
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 500),
            'consented_at' => Carbon::now(),
        ]);

        return response()->json([
            'code' => 200,
            'msg' => 'ok',
            'data' => [
                'id' => $record->id,
                'consented_at' => $record->consented_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * 获取用户最新同意记录
     *
     * GET /api/consent/latest
     */
    public function latest(Request $request): JsonResponse
    {
        $records = UserConsentRecord::where('user_id', $request->user()->id)
            ->orderByDesc('consented_at')
            ->get()
            ->groupBy('consent_type')
            ->map(fn ($group) => $group->first());

        return response()->json([
            'code' => 200,
            'msg' => 'ok',
            'data' => $records,
        ]);
    }
}
