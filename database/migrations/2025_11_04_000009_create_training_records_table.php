<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 训练记录表
 * 
 * 记录每个动作的每组训练数据
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id')->comment('训练会话ID');
            $table->unsignedBigInteger('exercise_id')->comment('动作ID');
            $table->integer('set_number')->comment('组数');
            $table->integer('reps')->comment('次数');
            $table->decimal('weight', 8, 2)->nullable()->comment('重量（kg）');
            $table->integer('rpe')->nullable()->comment('RPE（1-10）');
            $table->integer('rest_seconds')->nullable()->comment('组间休息（秒）');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();
            
            $table->foreign('session_id')->references('id')->on('training_sessions')->onDelete('cascade');
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');
            $table->index('session_id');
            $table->index('exercise_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_records');
    }
};

































