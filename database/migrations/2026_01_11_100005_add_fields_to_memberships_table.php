<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 扩展memberships表 - 会员自动化控制系统
 * 
 * 添加字段：original_price, is_first_purchase
 * 
 * @version v1.0.0
 * @date 2026-01-11
 * @author 薛小川
 * @requirements 9.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            // 原价（用于显示折扣）
            if (!Schema::hasColumn('memberships', 'original_price')) {
                $table->decimal('original_price', 10, 2)->default(0)
                    ->after('price')
                    ->comment('原价（元）');
            }
            
            // 是否为首充优惠套餐
            if (!Schema::hasColumn('memberships', 'is_first_purchase')) {
                $table->boolean('is_first_purchase')->default(false)
                    ->after('limits')
                    ->comment('是否为首充优惠套餐');
            }
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            if (Schema::hasColumn('memberships', 'original_price')) {
                $table->dropColumn('original_price');
            }
            if (Schema::hasColumn('memberships', 'is_first_purchase')) {
                $table->dropColumn('is_first_purchase');
            }
        });
    }
};
