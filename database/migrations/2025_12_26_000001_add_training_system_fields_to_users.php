<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 扩展 users 表，添加智能训练系统所需字段
 * 
 * 新增字段：
 * - preferred_training_time: 时间偏好（工作日晚间、午休时间等）
 * - body_type: 体型分类（thin/normal/overweight/muscular）
 * - user_type: 用户类型（student/worker/other）
 * - campus_name: 学校名称（大学生用户）
 * - personal_volume_multiplier: 个性化容量系数（0.7-1.5）
 * - personal_recovery_factor: 个性化恢复系数
 * - last_volume_adjusted_at: 上次容量调整时间
 * - consecutive_training_weeks: 连续训练周数
 * 
 * @version 1.0.0
 * @date 2025-12-26
 * @requirements 6.1, 7.1-7.5, 数据库变更
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 时间偏好
            $table->string('preferred_training_time', 50)
                ->nullable()
                ->after('preferences')
                ->comment('时间偏好：evening/lunch/morning/between_classes');
            
            // 体型分类
            $table->enum('body_type', ['thin', 'normal', 'overweight', 'muscular'])
                ->nullable()
                ->after('preferred_training_time')
                ->comment('体型分类');
            
            // 用户类型
            $table->enum('user_type', ['student', 'worker', 'other'])
                ->default('other')
                ->after('body_type')
                ->comment('用户类型');
            
            // 学校名称（大学生用户）
            $table->string('campus_name', 100)
                ->nullable()
                ->after('user_type')
                ->comment('学校名称（大学生用户）');
            
            // 个性化容量系数（0.7-1.5）
            $table->decimal('personal_volume_multiplier', 3, 2)
                ->default(1.00)
                ->after('campus_name')
                ->comment('个性化容量系数（0.7-1.5）');
            
            // 个性化恢复系数
            $table->decimal('personal_recovery_factor', 3, 2)
                ->default(1.00)
                ->after('personal_volume_multiplier')
                ->comment('个性化恢复系数');
            
            // 上次容量调整时间
            $table->timestamp('last_volume_adjusted_at')
                ->nullable()
                ->after('personal_recovery_factor')
                ->comment('上次容量调整时间');
            
            // 连续训练周数
            $table->integer('consecutive_training_weeks')
                ->default(0)
                ->after('last_volume_adjusted_at')
                ->comment('连续训练周数');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_training_time',
                'body_type',
                'user_type',
                'campus_name',
                'personal_volume_multiplier',
                'personal_recovery_factor',
                'last_volume_adjusted_at',
                'consecutive_training_weeks'
            ]);
        });
    }
};
