<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 扩展user_memberships表 - 会员自动化控制系统
 * 
 * 添加字段：auto_renew, status
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 11.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_memberships', function (Blueprint $table) {
            // 自动续费
            if (!Schema::hasColumn('user_memberships', 'auto_renew')) {
                $table->boolean('auto_renew')->default(false)
                    ->after('expires_at')
                    ->comment('是否自动续费');
            }
            
            // 状态（替代is_active，更精细的状态控制）
            if (!Schema::hasColumn('user_memberships', 'status')) {
                $table->enum('status', ['active', 'expired', 'cancelled'])->default('active')
                    ->after('auto_renew')
                    ->comment('状态：active/expired/cancelled');
            }
            
            // 添加expires_at索引（用于到期检查任务）
            $table->index('expires_at', 'idx_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_memberships', function (Blueprint $table) {
            if (Schema::hasColumn('user_memberships', 'auto_renew')) {
                $table->dropColumn('auto_renew');
            }
            if (Schema::hasColumn('user_memberships', 'status')) {
                $table->dropColumn('status');
            }
            $table->dropIndex('idx_expires_at');
        });
    }
};
