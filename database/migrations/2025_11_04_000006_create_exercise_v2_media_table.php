<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 动作媒体资源表
 * 
 * 存储动作的图片、视频、缩略图
 * 支持多角度、多性别的媒体资源
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_v2_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exercise_id');
            $table->enum('media_type', ['image', 'video', 'thumbnail'])->comment('媒体类型');
            $table->string('cdn_url')->nullable()->comment('CDN URL');
            $table->string('local_path')->nullable()->comment('本地路径');
            $table->integer('file_size')->nullable()->comment('文件大小（字节）');
            $table->integer('duration')->nullable()->comment('时长（秒，仅视频）');
            $table->integer('display_order')->default(0)->comment('显示顺序');
            $table->timestamps();
            
            $table->foreign('exercise_id')->references('id')->on('exercises')->onDelete('cascade');
            $table->index(['exercise_id', 'media_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_v2_media');
    }
};



































