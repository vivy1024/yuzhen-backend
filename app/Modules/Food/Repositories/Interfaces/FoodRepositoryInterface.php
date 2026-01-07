<?php

namespace App\Modules\Food\Repositories\Interfaces;

use App\Modules\Food\Models\Food;

/**
 * Food Repository Interface
 *
 * 食物库数据访问层接口
 * 
 * @version 1.0.0
 * @date 2026-01-04
 */
interface FoodRepositoryInterface
{
    /**
     * 根据ID查找食物
     */
    public function findById(int $id): ?Food;

    /**
     * 根据食物编码查找
     */
    public function findByCode(string $code): ?Food;

    /**
     * 分页获取食物列表
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /**
     * 搜索食物
     */
    public function search(string $keyword, int $page, int $perPage): array;

    /**
     * 获取分类列表
     */
    public function getCategories(): array;

    /**
     * 获取小类列表
     */
    public function getSubcategories(?string $category = null): array;

    /**
     * 统计总数
     */
    public function count(array $filters = []): int;
}
