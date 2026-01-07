<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['account', 'training', 'membership', 'technical'])
                ->default('technical')
                ->comment('分类：账号、训练、会员、技术');
            $table->string('question', 500)->comment('问题');
            $table->text('answer')->comment('答案');
            $table->integer('order')->default(0)->comment('排序');
            $table->integer('helpful_count')->default(0)->comment('有帮助数');
            $table->integer('not_helpful_count')->default(0)->comment('无帮助数');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();
            
            $table->index('category');
            $table->index('is_active');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
