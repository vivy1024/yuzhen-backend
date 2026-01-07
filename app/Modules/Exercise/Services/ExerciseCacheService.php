<?php

namespace App\Modules\Exercise\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Exercise Cache Service
 * 
 * 动作库缓存管理服务
 */
class ExerciseCacheService
{
    const CACHE_PREFIX = 'exercise:';
    const CACHE_TTL = 3600; // 1小时
    
    /**
     * 生成列表缓存键
     */
    public function generateListCacheKey(array $filters, int $page, int $perPage): string
    {
        $filterStr = md5(json_encode($filters));
        return self::CACHE_PREFIX . "list:{$filterStr}:{$page}:{$perPage}";
    }

    /**
     * 生成详情缓存键
     */
    public function generateDetailCacheKey(int $id): string
    {
        return self::CACHE_PREFIX . "detail:{$id}";
    }

    /**
     * 生成筛选选项缓存键
     */
    public function generateFilterOptionsCacheKey(): string
    {
        return self::CACHE_PREFIX . "filter_options";
    }

    /**
     * 清除动作缓存
     */
    public function clearExerciseCache(int $id): void
    {
        $pattern = self::CACHE_PREFIX . "detail:{$id}";
        Cache::forget($pattern);
        
        // 清除列表缓存
        $this->clearListCache();
    }

    /**
     * 清除列表缓存
     */
    public function clearListCache(): void
    {
        // 由于使用了md5，这里只能清除所有exercise相关缓存
        // 在生产环境可以使用Redis的pattern删除
        Cache::tags(['exercise:list'])->flush();
    }

    /**
     * 清除所有缓存
     */
    public function clearAllCache(): void
    {
        Cache::tags(['exercise'])->flush();
    }
}

