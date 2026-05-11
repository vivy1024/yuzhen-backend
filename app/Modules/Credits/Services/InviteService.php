<?php

namespace App\Modules\Credits\Services;

use App\Modules\Credits\Models\CreditLedger;
use App\Modules\Credits\Models\InviteRecord;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * InviteService - 邀请好友积分服务
 *
 * 处理邀请码生成、邀请注册、邀请统计
 */
class InviteService
{
    private CreditService $creditService;

    // 邀请奖励配置
    private const INVITER_CREDITS = '20.000000';  // 邀请人获得
    private const INVITEE_CREDITS = '10.000000';  // 被邀请人获得

    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    /**
     * 生成唯一邀请码（8位大写字母+数字）
     *
     * 排除易混淆字符：I/1/O/0
     */
    public function generateInviteCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (User::where('invite_code', $code)->exists());

        return $code;
    }

    /**
     * 处理邀请注册
     *
     * 注册时调用，验证邀请码并发放双方积分
     *
     * @param int $inviteeId 被邀请人（新注册用户）ID
     * @param string $inviteCode 邀请码
     * @return array { success: bool, message: string, inviter_id?: int }
     */
    public function processInvite(int $inviteeId, string $inviteCode): array
    {
        // 查找邀请码对应的用户
        $inviter = User::where('invite_code', strtoupper($inviteCode))->first();

        if (!$inviter) {
            Log::warning('邀请码无效', ['invitee_id' => $inviteeId, 'invite_code' => $inviteCode]);
            return ['success' => false, 'message' => '邀请码无效'];
        }

        // 防止自邀请
        if ($inviter->id === $inviteeId) {
            Log::warning('尝试自邀请', ['user_id' => $inviteeId]);
            return ['success' => false, 'message' => '不能使用自己的邀请码'];
        }

        // 检查是否已被邀请过
        $alreadyInvited = InviteRecord::where('invitee_id', $inviteeId)->exists();
        if ($alreadyInvited) {
            return ['success' => false, 'message' => '已使用过邀请码'];
        }

        return DB::transaction(function () use ($inviter, $inviteeId) {
            // 更新被邀请人的 invited_by 字段
            User::where('id', $inviteeId)->update(['invited_by' => $inviter->id]);

            // 发放邀请人积分
            $this->creditService->earn(
                $inviter->id,
                self::INVITER_CREDITS,
                CreditLedger::SOURCE_INVITE,
                '邀请好友注册奖励'
            );

            // 发放被邀请人积分
            $this->creditService->earn(
                $inviteeId,
                self::INVITEE_CREDITS,
                CreditLedger::SOURCE_INVITE,
                '受邀注册奖励'
            );

            // 记录邀请关系
            InviteRecord::create([
                'inviter_id' => $inviter->id,
                'invitee_id' => $inviteeId,
                'credits_given_inviter' => self::INVITER_CREDITS,
                'credits_given_invitee' => self::INVITEE_CREDITS,
                'created_at' => now(),
            ]);

            Log::info('邀请注册成功', [
                'inviter_id' => $inviter->id,
                'invitee_id' => $inviteeId,
                'inviter_credits' => self::INVITER_CREDITS,
                'invitee_credits' => self::INVITEE_CREDITS,
            ]);

            return [
                'success' => true,
                'message' => '邀请码使用成功',
                'inviter_id' => $inviter->id,
            ];
        });
    }

    /**
     * 获取用户的邀请统计
     *
     * @param int $userId
     * @return array { total_invited: int, total_credits_earned: string, records: array }
     */
    public function getInviteStats(int $userId): array
    {
        $records = InviteRecord::where('inviter_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        $totalInvited = $records->count();
        $totalCreditsEarned = $records->sum('credits_given_inviter');

        return [
            'total_invited' => $totalInvited,
            'total_credits_earned' => number_format($totalCreditsEarned, 6, '.', ''),
            'records' => $records->map(fn(InviteRecord $record) => [
                'invitee_id' => $record->invitee_id,
                'credits_earned' => $record->credits_given_inviter,
                'created_at' => $record->created_at?->format('Y-m-d H:i:s'),
            ])->toArray(),
        ];
    }

    /**
     * 获取用户的邀请码
     *
     * 如果用户还没有邀请码则自动生成
     *
     * @param int $userId
     * @return string
     */
    public function getUserInviteCode(int $userId): string
    {
        $user = User::findOrFail($userId);

        if (!$user->invite_code) {
            $code = $this->generateInviteCode();
            $user->invite_code = $code;
            $user->save();
        }

        return $user->invite_code;
    }
}
