<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建 personal_bests 表 - 个人最佳记录表
 * 
 * 用于追踪用户每个动作的最佳表现：
 * - 最佳重量
 * - 最佳次数
 * - 达成日期
 * - 上次使用重量和日期（用于渐进过载计算）
 * 
 * @version 1.0.0
 * @date 2025-12-26
 * @requirements 6.4, 8.1-8.4
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_bests', function (Blueprint $table) {
            $table->id();
            
            // 用户关联
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('用户ID');
            
            // 动作ID（Neo4j Exercise节点ID）
            $table->string('exercise_id', 50)
                ->comment('Neo4j Exercise节点ID');
            
            // 动作名称（冗余存储，方便查询）
            $table->string('exercise_name', 100)
                ->nullable()
                ->comment('动作名称');
            
            // 最佳重量（kg）
            $table->decimal('best_weight', 5, 2)
                ->nullable()
                ->comment('最佳重量（kg）');
            
            // 最佳次数
            $table->integer('best_reps')
                ->nullable()
                ->comment('最佳次数');
            
            // 最佳1RM估算值
            $table->decimal('estimated_1rm', 5, 2)
                ->nullable()
                ->comment('估算1RM（kg）');
            
            // 达成日期
            $table->date('achieved_date')
                ->nullable()
                ->comment('达成日期');
            
            // 上次使用重量
            $table->decimal('last_used_weight', 5, 2)
                ->nullable()
                ->comment('上次使用重量（kg）');
            
            // 上次使用日期
            $table->date('last_used_date')
                ->nullable()
                ->comment('上次使用日期');
            
            // 使用次数（用于判断熟悉程度）
            $table->integer('usage_count')
                ->default(0)
                ->comment('使用次数');
            
            $table->timestamps();
            
            // 唯一约束：每个用户每个动作只有一条记录
            $table->unique(['user_id', 'exercise_id'], 'uk_user_exercise');
            
            // 索引
            $table->index('user_id', 'idx_user_id');
            $table->index('exercise_id', 'idx_exercise_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_bests');
    }
};
