<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 扩展users表 - 会员自动化控制系统
 * 
 * 添加会员相关字段：membership_tier(enum), first_purchase_used, referral_code
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 8.4
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 修改membership_tier为enum类型（如果需要）
            // 注意：MySQL不支持直接修改为enum，需要先删除再添加
            // 这里我们添加新字段，保留原有字段兼容性
            
            // 首充优惠是否已使用
            if (!Schema::hasColumn('users', 'first_purchase_used')) {
                $table->boolean('first_purchase_used')->default(false)
                    ->after('membership_tier')
                    ->comment('是否已使用首充优惠');
            }
            
            // 推荐码
            if (!Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 20)->nullable()->unique()
                    ->after('first_purchase_used')
                    ->comment('用户推荐码');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'first_purchase_used')) {
                $table->dropColumn('first_purchase_used');
            }
            if (Schema::hasColumn('users', 'referral_code')) {
                $table->dropColumn('referral_code');
            }
        });
    }
};
