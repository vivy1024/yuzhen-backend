<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * 邀请系统迁移
 *
 * - users 表添加 invite_code 和 invited_by 字段
 * - 新建 invite_records 表
 * - 为已有用户生成 invite_code
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. users 表添加邀请相关字段
        Schema::table('users', function (Blueprint $table) {
            $table->string('invite_code', 8)->nullable()->unique()->after('referral_code')->comment('邀请码（8位大写字母+数字）');
            $table->unsignedBigInteger('invited_by')->nullable()->after('invite_code')->comment('邀请人用户ID');

            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
        });

        // 2. 新建 invite_records 表
        Schema::create('invite_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inviter_id')->comment('邀请人ID');
            $table->unsignedBigInteger('invitee_id')->comment('被邀请人ID');
            $table->decimal('credits_given_inviter', 10, 6)->default(0)->comment('邀请人获得积分');
            $table->decimal('credits_given_invitee', 10, 6)->default(0)->comment('被邀请人获得积分');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('inviter_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('invitee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('inviter_id');
            $table->unique('invitee_id'); // 每个用户只能被邀请一次
        });

        // 3. 为已有用户生成 invite_code
        $users = DB::table('users')->whereNull('invite_code')->get(['id']);
        foreach ($users as $user) {
            $code = $this->generateUniqueCode();
            DB::table('users')->where('id', $user->id)->update(['invite_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_records');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['invited_by']);
            $table->dropColumn(['invite_code', 'invited_by']);
        });
    }

    /**
     * 生成唯一的8位邀请码
     */
    private function generateUniqueCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 排除易混淆字符 I/1/O/0
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (DB::table('users')->where('invite_code', $code)->exists());

        return $code;
    }
};
