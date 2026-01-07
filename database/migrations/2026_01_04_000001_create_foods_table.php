<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建食物表
 * 
 * 数据来源：《中国食物成分表》(1,851条)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            
            // 基础信息
            $table->string('food_code', 20)->unique()->comment('食物编码');
            $table->string('name', 100)->comment('食物名称');
            $table->string('category', 50)->index()->comment('大类（如：畜肉类及其制品）');
            $table->string('subcategory', 50)->nullable()->index()->comment('小类（如：猪）');
            
            // 基础营养素（每100g）
            $table->decimal('edible', 5, 1)->default(100)->comment('可食部(%)');
            $table->decimal('water', 5, 1)->nullable()->comment('水分(g)');
            $table->decimal('energy_kcal', 6, 1)->nullable()->comment('能量(kcal)');
            $table->decimal('energy_kj', 7, 1)->nullable()->comment('能量(kJ)');
            $table->decimal('protein', 5, 2)->nullable()->comment('蛋白质(g)');
            $table->decimal('fat', 5, 2)->nullable()->comment('脂肪(g)');
            $table->decimal('carbohydrate', 5, 2)->nullable()->comment('碳水化合物(g)');
            $table->decimal('dietary_fiber', 5, 2)->nullable()->comment('膳食纤维(g)');
            $table->decimal('cholesterol', 6, 1)->nullable()->comment('胆固醇(mg)');
            $table->decimal('ash', 5, 2)->nullable()->comment('灰分(g)');
            
            // 维生素
            $table->decimal('vitamin_a', 7, 2)->nullable()->comment('维生素A(μg)');
            $table->decimal('carotene', 7, 2)->nullable()->comment('胡萝卜素(μg)');
            $table->decimal('retinol', 7, 2)->nullable()->comment('视黄醇(μg)');
            $table->decimal('thiamin', 6, 3)->nullable()->comment('硫胺素/维生素B1(mg)');
            $table->decimal('riboflavin', 5, 3)->nullable()->comment('核黄素/维生素B2(mg)');
            $table->decimal('niacin', 6, 3)->nullable()->comment('烟酸(mg)');
            $table->decimal('vitamin_c', 6, 2)->nullable()->comment('维生素C(mg)');
            $table->decimal('vitamin_e_total', 6, 3)->nullable()->comment('维生素E总量(mg)');
            
            // 矿物质
            $table->decimal('calcium', 7, 2)->nullable()->comment('钙(mg)');
            $table->decimal('phosphorus', 7, 2)->nullable()->comment('磷(mg)');
            $table->decimal('potassium', 7, 2)->nullable()->comment('钾(mg)');
            $table->decimal('sodium', 7, 2)->nullable()->comment('钠(mg)');
            $table->decimal('magnesium', 7, 2)->nullable()->comment('镁(mg)');
            $table->decimal('iron', 6, 3)->nullable()->comment('铁(mg)');
            $table->decimal('zinc', 6, 3)->nullable()->comment('锌(mg)');
            $table->decimal('selenium', 7, 3)->nullable()->comment('硒(μg)');
            $table->decimal('copper', 6, 3)->nullable()->comment('铜(mg)');
            $table->decimal('manganese', 6, 3)->nullable()->comment('锰(mg)');
            
            // 扩展信息
            $table->string('remark', 255)->nullable()->comment('备注（产地等）');
            $table->integer('gi_value')->nullable()->comment('GI值');
            $table->string('price_level', 10)->nullable()->comment('价格等级');
            
            // 统计
            $table->integer('view_count')->default(0)->comment('浏览次数');
            
            $table->timestamps();
            
            // 索引
            $table->index('name');
            $table->index('energy_kcal');
            $table->index('protein');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
