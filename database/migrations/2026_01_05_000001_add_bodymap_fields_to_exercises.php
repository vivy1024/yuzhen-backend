<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 添加人体图和肌肉详细字段到 exercises 表
 * 
 * @version 1.0.0
 * @date 2026-01-05
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            // 变体相关
            $table->unsignedBigInteger('variation_of')->nullable()->comment('变体来源ID');
            $table->json('variations')->nullable()->comment('变体列表');
            $table->json('joints')->nullable()->comment('涉及关节');
            
            // 人体图
            $table->json('body_map_images')->nullable()->comment('人体图远程URL');
            $table->json('body_map_images_local')->nullable()->comment('人体图本地路径');
            
            // 肌肉详细信息
            $table->json('muscles_primary_en')->nullable()->comment('主要肌肉英文');
            $table->json('muscles_primary_zh')->nullable()->comment('主要肌肉中文');
            $table->json('muscles_secondary_en')->nullable()->comment('次要肌肉英文');
            $table->json('muscles_secondary_zh')->nullable()->comment('次要肌肉中文');
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn([
                'variation_of',
                'variations',
                'joints',
                'body_map_images',
                'body_map_images_local',
                'muscles_primary_en',
                'muscles_primary_zh',
                'muscles_secondary_en',
                'muscles_secondary_zh',
            ]);
        });
    }
};
