<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 创建用户反馈表
     */
    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['feature', 'bug', 'question', 'other'])->default('other')->comment('反馈类型');
            $table->text('content')->comment('反馈内容');
            $table->json('images')->nullable()->comment('截图URL数组');
            $table->string('contact', 100)->nullable()->comment('联系方式');
            $table->enum('status', ['pending', 'processing', 'resolved', 'closed'])->default('pending')->comment('处理状态');
            $table->text('reply')->nullable()->comment('官方回复');
            $table->timestamp('reply_at')->nullable()->comment('回复时间');
            $table->foreignId('reply_by')->nullable()->constrained('users')->onDelete('set null')->comment('回复人');
            $table->timestamps();
            
            // 索引
            $table->index('user_id');
            $table->index('status');
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
