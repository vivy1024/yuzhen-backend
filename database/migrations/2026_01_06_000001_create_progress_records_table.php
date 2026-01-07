<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建 progress_records 表 - 进度记录表
 * 
 * 用于存储用户的体重、体脂、围度等身体数据历史记录
 * 支持进度追踪和趋势分析
 * 
 * @version 1.0.0
 * @date 2026-01-06
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_records', function (Blueprint $table) {
            $table->id();
            
            // 用户关联
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('用户ID');
            
            // 记录日期
            $table->date('date')
                ->comment('记录日期');
            
            // 体重（kg）
            $table->decimal('weight', 5, 2)
                ->comment('体重（kg）');
            
            // 体脂率（%）
            $table->decimal('body_fat', 4, 1)
                ->nullable()
                ->comment('体脂率（%）');
            
            // FFMI（自动计算）
            $table->decimal('ffmi', 4, 2)
                ->nullable()
                ->comment('FFMI指数');
            
            // 瘦体重（kg，自动计算）
            $table->decimal('lean_body_mass', 5, 2)
                ->nullable()
                ->comment('瘦体重（kg）');
            
            // 围度数据（JSON）
            $table->json('measurements')
                ->nullable()
                ->comment('围度数据：chest, waist, hips, arms, thighs');
            
            // 照片（JSON数组）
            $table->json('photos')
                ->nullable()
                ->comment('进度照片URL数组');
            
            // 备注
            $table->text('notes')
                ->nullable()
                ->comment('备注');
            
            $table->timestamps();
            
            // 索引
            $table->index(['user_id', 'date'], 'idx_user_date');
            $table->index('date', 'idx_date');
            
            // 唯一约束：每个用户每天只能有一条记录
            $table->unique(['user_id', 'date'], 'uk_user_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_records');
    }
};
