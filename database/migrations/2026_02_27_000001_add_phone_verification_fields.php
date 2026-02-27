<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * 添加手机号验证相关字段
 *
 * 注意：此迁移会添加唯一约束，执行前请确保无重复手机号
 * 可先运行: SELECT phone, COUNT(*) FROM users WHERE phone IS NOT NULL GROUP BY phone HAVING COUNT(*) > 1;
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 先检查并清理重复的手机号（保留最早创建的记录）
        $duplicates = DB::table('users')
            ->select('phone')
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            // 保留最早创建的记录，其他设为NULL
            $users = DB::table('users')
                ->where('phone', $duplicate->phone)
                ->orderBy('created_at', 'asc')
                ->get();

            $keepUserId = $users->first()->id;
            foreach ($users->skip(1) as $user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['phone' => null]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            // 添加唯一约束（使用 change() 修改现有字段）
            // SQLite 不支持 change()，需要在 MySQL/PostgreSQL 环境运行
            $table->string('phone', 20)->nullable()->unique()->change();

            // 添加验证时间字段
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->timestamp('phone_bound_at')->nullable()->after('phone_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 移除唯一约束
            $table->dropUnique(['phone']);

            // 移除新增字段
            $table->dropColumn(['phone_verified_at', 'phone_bound_at']);
        });
    }
};