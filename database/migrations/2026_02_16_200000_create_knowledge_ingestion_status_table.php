<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_ingestion_status', function (Blueprint $table) {
            $table->id();
            $table->string('document_id', 64)->unique()->comment('文档唯一标识 MD5(filepath)');
            $table->string('filename', 255)->comment('原始文件名');
            $table->string('filepath', 512)->nullable()->comment('完整路径');
            $table->enum('source_type', ['bilibili_subtitle', 'pdf_textbook', 'markdown_note'])->comment('来源类型');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('chunk_count')->default(0)->comment('分割后的chunk数');
            $table->unsignedInteger('vector_count')->default(0)->comment('成功入库的向量数');
            $table->unsignedInteger('total_chars')->default(0)->comment('总字符数');
            $table->text('error_message')->nullable()->comment('失败时的错误信息');
            $table->string('qdrant_collection', 100)->default('training_knowledge');
            $table->string('embedding_model', 100)->default('thenlper/gte-large-zh');
            $table->unsignedInteger('processing_time_ms')->default(0)->comment('处理耗时毫秒');
            $table->json('metadata')->nullable()->comment('额外元数据');
            $table->timestamps();

            $table->index('status', 'idx_status');
            $table->index('source_type', 'idx_source_type');
            $table->index('filename', 'idx_filename');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_ingestion_status');
    }
};
