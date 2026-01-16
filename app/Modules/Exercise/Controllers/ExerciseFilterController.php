<?php

namespace App\Modules\Exercise\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Exercise\Services\ExerciseFilterService;
use App\Modules\Exercise\Services\ExerciseCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Exercise Filter Controller
 * 
 * 动作筛选API控制器
 */
class ExerciseFilterController extends BaseController
{
    protected ExerciseFilterService $filterService;
    protected ExerciseCacheService $cacheService;
    
    public function __construct(
        ExerciseFilterService $filterService,
        ExerciseCacheService $cacheService
    ) {
        $this->filterService = $filterService;
        $this->cacheService = $cacheService;
    }

    /**
     * 获取筛选选项
     * 
     * 添加HTTP缓存头：24小时缓存
     */
    public function options(): JsonResponse
    {
        try {
            $options = $this->filterService->getFilterOptions();
            
            $response = $this->success($options, '获取筛选选项成功');
            
            // 添加HTTP缓存头（24小时 = 86400秒）
            $response->header('Cache-Control', 'public, max-age=86400');
            $response->header('Expires', gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
            
            return $response;
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取筛选选项');
        }
    }

    /**
     * 清除筛选选项缓存
     * 
     * GET /api/exercises-v2/filter-options/clear-cache
     * 用于数据更新后刷新缓存
     */
    public function clearCache(): JsonResponse
    {
        try {
            $cacheKey = $this->cacheService->generateFilterOptionsCacheKey();
            Cache::forget($cacheKey);
            
            // 重新获取并缓存
            $options = $this->filterService->getFilterOptions();
            
            return $this->success([
                'cleared' => true,
                'cache_key' => $cacheKey,
                'new_data_count' => [
                    'muscle' => count($options['muscle'] ?? []),
                    'equipment' => count($options['equipment'] ?? []),
                    'difficulty' => count($options['difficulty'] ?? []),
                ]
            ], '缓存已清除并重新加载');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '清除缓存');
        }
    }
}

