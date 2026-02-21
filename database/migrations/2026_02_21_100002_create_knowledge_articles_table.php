<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200)->comment('知识标题');
            $table->text('summary')->comment('摘要（200字以内）');
            $table->longText('content')->comment('正文Markdown');
            $table->unsignedBigInteger('category_id')->comment('所属分类');
            $table->string('source_book', 200)->nullable()->comment('来源书名');
            $table->string('source_chapter', 100)->nullable()->comment('来源章节');
            $table->string('source_page', 20)->nullable()->comment('来源页码范围');
            $table->json('tags')->nullable()->comment('标签数组 ["力量训练","周期化"]');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->comment('状态');
            $table->enum('difficulty', ['beginner', 'intermediate', 'advanced'])->default('intermediate')->comment('难度等级');
            $table->unsignedInteger('view_count')->default(0)->comment('浏览次数');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('knowledge_categories')->onDelete('cascade');
            $table->index('category_id', 'idx_category');
            $table->index('status', 'idx_status');
            $table->index('difficulty', 'idx_difficulty');
            $table->fullText(['title', 'summary', 'content'], 'ft_knowledge');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
    }
};
