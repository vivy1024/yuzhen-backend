<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 训练进度表
 * 
 * 追踪用户在特定动作上的进步
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('exercise_id');
            $table->decimal('max_weight', 8, 2)->nullable()->comment('最大重量（kg）');
            $table->integer('max_reps')->nullable()->comment('最大次数');
            $table->decimal('estimated_1rm', 8, 2)->nullable()->comment('估算1RM');
            $table->decimal('total_volume', 10, 2)->nullable()->comment('总容量（kg）');
            $table->integer('total_sets')->default(0)->comment('总组数');
            $table->integer('total_reps')->default(0)->comment('总次数');
            $table->date('last_trained_at')->nullable()->comment('最后训练日期');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');
            $table->unique(['user_id', 'exercise_id']);
            $table->index('last_trained_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_progress');
    }
};

































