<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('分类名称');
            $table->string('slug', 50)->unique()->comment('URL友好标识');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父分类ID，NULL为顶级');
            $table->string('icon', 20)->nullable()->comment('图标emoji');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序权重');
            $table->text('description')->nullable()->comment('分类描述');
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('knowledge_categories')->onDelete('cascade');
            $table->index('parent_id', 'idx_parent');
            $table->index('sort_order', 'idx_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_categories');
    }
};
