<?php

namespace App\Modules\Food\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Food\Services\FoodService;
use App\Modules\Food\Resources\FoodResource;
use App\Modules\Food\Resources\FoodDetailResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Food Controller
 * 
 * 食物库API控制器
 * 
 * @version 1.0.0
 * @date 2026-01-04
 */
class FoodController extends BaseController
{
    protected FoodService $foodService;
    
    public function __construct(FoodService $foodService)
    {
        $this->foodService = $foodService;
    }

    /**
     * 获取食物列表
     * 
     * GET /api/foods
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'category' => $request->input('category'),
                'subcategory' => $request->input('subcategory'),
                'search' => $request->input('search') ?? $request->input('query'),
                'high_protein' => $request->boolean('high_protein'),
                'low_calorie' => $request->boolean('low_calorie'),
                'sort_by' => $request->input('sort_by', 'id'),
                'sort_order' => $request->input('sort_order', 'asc'),
            ];
            
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            
            $result = $this->foodService->getList($filters, $page, $perPage);
            
            return $this->page(
                FoodResource::collection($result['rows'])->resolve(),
                $result['total'],
                $result['page'],
                $result['per_page'],
                '获取食物列表成功'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取食物列表');
        }
    }

    /**
     * 获取食物详情
     * 
     * GET /api/foods/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $food = $this->foodService->getDetail($id);
            
            if (!$food) {
                return $this->fail('食物不存在', 404);
            }
            
            return $this->success(
                new FoodDetailResource($food),
                '获取食物详情成功'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取食物详情');
        }
    }

    /**
     * 搜索食物
     * 
     * GET /api/foods/search
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $keyword = $request->input('q', '');
            
            if (empty($keyword)) {
                return $this->fail('搜索关键词不能为空', 400);
            }
            
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            
            $result = $this->foodService->search($keyword, $page, $perPage);
            
            return $this->success([
                'keyword' => $keyword,
                'results' => FoodResource::collection($result['rows'])->resolve(),
                'total' => $result['total']
            ], '搜索完成');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '搜索食物');
        }
    }

    /**
     * 获取分类列表
     * 
     * GET /api/foods/categories
     * 添加HTTP缓存头：24小时缓存
     */
    public function categories(): JsonResponse
    {
        try {
            $categories = $this->foodService->getCategories();
            
            $response = $this->success($categories, '获取分类列表成功');
            
            // 添加HTTP缓存头（24小时 = 86400秒）
            $response->header('Cache-Control', 'public, max-age=86400');
            $response->header('Expires', gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
            
            return $response;
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取分类列表');
        }
    }

    /**
     * 获取筛选选项
     * 
     * GET /api/foods/filter-options
     * 添加HTTP缓存头：24小时缓存
     */
    public function filterOptions(): JsonResponse
    {
        try {
            $options = $this->foodService->getFilterOptions();
            
            $response = $this->success($options, '获取筛选选项成功');
            
            // 添加HTTP缓存头（24小时 = 86400秒）
            $response->header('Cache-Control', 'public, max-age=86400');
            $response->header('Expires', gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
            
            return $response;
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取筛选选项');
        }
    }
}
