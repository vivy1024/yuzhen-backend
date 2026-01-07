<?php

namespace App\Modules\Food\Repositories;

use App\Modules\Food\Models\Food;
use App\Modules\Food\Repositories\Interfaces\FoodRepositoryInterface;
use App\Infrastructure\Database\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Food Repository
 *
 * 食物库数据访问层实现
 * 
 * @version 1.0.0
 * @date 2026-01-04
 */
class FoodRepository extends BaseRepository implements FoodRepositoryInterface
{
    protected $model = Food::class;

    /**
     * 根据ID查找食物
     */
    public function findById(int $id): ?Food
    {
        return Food::find($id);
    }

    /**
     * 根据食物编码查找
     */
    public function findByCode(string $code): ?Food
    {
        return Food::where('food_code', $code)->first();
    }

    /**
     * 分页获取食物列表
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $query = Food::query();

        // 分类筛选
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // 小类筛选
        if (!empty($filters['subcategory'])) {
            $query->where('subcategory', $filters['subcategory']);
        }

        // 关键词搜索
        if (!empty($filters['search']) || !empty($filters['query'])) {
            $keyword = $filters['search'] ?? $filters['query'];
            $query->where('name', 'like', "%{$keyword}%");
        }

        // 高蛋白筛选
        if (!empty($filters['high_protein'])) {
            $query->where('protein', '>=', 20);
        }

        // 低热量筛选
        if (!empty($filters['low_calorie'])) {
            $query->where('energy_kcal', '<=', 100);
        }

        // 排序
        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // 分页
        $total = $query->count();
        $rows = $query->skip(($page - 1) * $perPage)
                      ->take($perPage)
                      ->get();

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * 搜索食物
     */
    public function search(string $keyword, int $page, int $perPage): array
    {
        return $this->paginate(['search' => $keyword], $page, $perPage);
    }

    /**
     * 获取分类列表
     */
    public function getCategories(): array
    {
        return Food::select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->category,
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * 获取小类列表
     */
    public function getSubcategories(?string $category = null): array
    {
        $query = Food::select('category', 'subcategory', DB::raw('COUNT(*) as count'))
            ->whereNotNull('subcategory')
            ->groupBy('category', 'subcategory')
            ->orderBy('category')
            ->orderBy('count', 'desc');

        if ($category) {
            $query->where('category', $category);
        }

        return $query->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category,
                    'subcategory' => $item->subcategory,
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * 统计总数
     */
    public function count(array $filters = []): int
    {
        $query = Food::query();

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['subcategory'])) {
            $query->where('subcategory', $filters['subcategory']);
        }

        return $query->count();
    }
}
