<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id')->comment('关联文章');
            $table->enum('ref_type', ['book', 'paper', 'guideline', 'website'])->default('book')->comment('引用类型');
            $table->string('title', 300)->comment('引用标题');
            $table->string('authors', 500)->nullable()->comment('作者列表');
            $table->unsignedSmallInteger('year')->nullable()->comment('出版年份');
            $table->string('doi', 100)->nullable()->comment('DOI标识');
            $table->string('chapter', 100)->nullable()->comment('章节');
            $table->string('page_range', 20)->nullable()->comment('页码范围');
            $table->string('isbn', 20)->nullable()->comment('ISBN');
            $table->timestamps();

            $table->foreign('article_id')->references('id')->on('knowledge_articles')->onDelete('cascade');
            $table->index('article_id', 'idx_article');
            $table->index('ref_type', 'idx_ref_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_references');
    }
};
