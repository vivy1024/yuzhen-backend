<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 训练会话表
 * 
 * 记录每次训练的完整信息
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plan_id')->nullable()->comment('关联的训练计划ID');
            $table->string('session_name')->comment('训练名称');
            $table->text('notes')->nullable()->comment('训练笔记');
            $table->integer('duration_minutes')->nullable()->comment('训练时长（分钟）');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped'])->default('pending');
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('training_plans')->onDelete('set null');
            $table->index(['user_id', 'status']);
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};

































