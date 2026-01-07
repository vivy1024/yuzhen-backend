<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为exercises表添加数据来源字段
 * 
 * 需求来源: Requirements 18.3
 * - 添加source字段标记数据来源
 * - 添加license_type字段标记授权类型
 * 
 * 数据来源类型:
 * - self_compiled: 自主整理
 * - public_standard: 公开标准
 * - academic_literature: 学术文献
 * - third_party_reference: 第三方参考
 * 
 * 授权类型:
 * - proprietary: 自有
 * - public_domain: 公共领域
 * - open_license: 开放授权
 * - commercial_license: 商业授权
 * - pending_confirmation: 待确认
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            // 数据来源字段
            $table->string('data_source', 50)->nullable()->after('rest_period')
                ->comment('数据来源类型: self_compiled/public_standard/academic_literature/third_party_reference');
            
            // 来源参考说明
            $table->string('source_reference', 255)->nullable()->after('data_source')
                ->comment('来源参考说明');
            
            // 授权类型
            $table->string('license_type', 50)->nullable()->after('source_reference')
                ->comment('授权类型: proprietary/public_domain/open_license/commercial_license/pending_confirmation');
            
            // 原始来源（如果是第三方数据）
            $table->string('original_source', 100)->nullable()->after('license_type')
                ->comment('原始数据来源（如MuscleWiki）');
            
            // 数据验证信息
            $table->timestamp('last_verified_at')->nullable()->after('original_source')
                ->comment('最后验证时间');
            $table->string('verified_by', 100)->nullable()->after('last_verified_at')
                ->comment('验证人');
            
            // 索引
            $table->index('data_source');
            $table->index('license_type');
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropIndex(['data_source']);
            $table->dropIndex(['license_type']);
            
            $table->dropColumn([
                'data_source',
                'source_reference',
                'license_type',
                'original_source',
                'last_verified_at',
                'verified_by',
            ]);
        });
    }
};
