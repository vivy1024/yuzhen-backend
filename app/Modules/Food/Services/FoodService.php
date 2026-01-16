<?php

namespace App\Modules\Food\Services;

use App\Modules\Food\Models\Food;
use App\Modules\Food\Repositories\Interfaces\FoodRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Food Service
 *
 * 食物库核心业务逻辑
 *
 * @version 1.0.0
 * @date 2026-01-04
 */
class FoodService
{
    protected FoodRepositoryInterface $repository;

    public function __construct(FoodRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * 获取食物列表（带缓存）
     */
    public function getList(array $filters, int $page = 1, int $perPage = 20): array
    {
        $cacheKey = 'foods:list:' . md5(json_encode($filters) . $page . $perPage);

        return Cache::remember($cacheKey, 3600, function () use ($filters, $page, $perPage) {
            return $this->repository->paginate($filters, $page, $perPage);
        });
    }

    /**
     * 获取食物详情
     */
    public function getDetail(int $id): ?Food
    {
        $food = $this->repository->findById($id);

        if ($food) {
            $food->incrementViewCount();
        }

        return $food;
    }

    /**
     * 搜索食物
     */
    public function search(string $keyword, int $page = 1, int $perPage = 20): array
    {
        return $this->repository->search($keyword, $page, $perPage);
    }

    /**
     * 获取分类列表（临时禁用缓存）
     */
    public function getCategories(): array
    {
        // 临时禁用缓存，直接从数据库获取数据
        // TODO: 调查生产环境缓存问题后恢复缓存
        return $this->repository->getCategories();
    }

    /**
     * 获取小类列表（带缓存）
     */
    public function getSubcategories(?string $category = null): array
    {
        $cacheKey = 'foods:subcategories:' . ($category ?? 'all');

        return Cache::remember($cacheKey, 86400, function () use ($category) {
            return $this->repository->getSubcategories($category);
        });
    }

    /**
     * 获取筛选选项
     */
    public function getFilterOptions(): array
    {
        return Cache::remember('foods:filter_options', 86400, function () {
            return [
                'categories' => $this->repository->getCategories(),
                'subcategories' => $this->repository->getSubcategories(),
            ];
        });
    }

    /**
     * 清除缓存
     */
    public function clearCache(): void
    {
        Cache::forget('foods:categories');
        Cache::forget('foods:subcategories:all');
        Cache::forget('foods:filter_options');
    }
}
