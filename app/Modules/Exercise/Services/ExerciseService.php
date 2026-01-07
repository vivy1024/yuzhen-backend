<?php

namespace App\Modules\Exercise\Services;

use App\Modules\Exercise\Models\Exercise;
use App\Modules\Exercise\Repositories\Interfaces\ExerciseRepositoryInterface;
use App\Modules\Exercise\Events\ExerciseViewed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * Exercise Service
 *
 * 动作库核心业务逻辑
 *
 * @version 2.0.1
 * @date 2025-11-02
 * @changes getDetail() 返回 Exercise 对象以适配 Resource
 */
class ExerciseService
{
    protected ExerciseRepositoryInterface $repository;
    protected ExerciseCacheService $cacheService;

    public function __construct(
        ExerciseRepositoryInterface $repository,
        ExerciseCacheService $cacheService
    ) {
        $this->repository = $repository;
        $this->cacheService = $cacheService;
    }

    /**
     * 获取动作列表（带缓存）
     */
    public function getList(array $filters, int $page = 1, int $perPage = 20): array
    {
        $cacheKey = $this->cacheService->generateListCacheKey($filters, $page, $perPage);

        return Cache::remember($cacheKey, 3600, function () use ($filters, $page, $perPage) {
            return $this->repository->paginate($filters, $page, $perPage);
        });
    }

    /**
     * 获取动作详情
     * 
     * @return Exercise|null 返回 Exercise 模型实例
     */
    public function getDetail(int $id): ?Exercise
    {
        $exercise = $this->repository->findById($id);

        if ($exercise) {
            // 触发查看事件
            Event::dispatch(new ExerciseViewed($exercise));
        }

        return $exercise;
    }

    /**
     * 搜索动作
     */
    public function search(string $keyword, int $page = 1, int $perPage = 20): array
    {
        return $this->repository->search($keyword, $page, $perPage);
    }

    /**
     * 获取推荐动作
     */
    public function getRecommended(int $userId, int $limit = 10): array
    {
        // TODO: 实现推荐算法
        return $this->repository->getPopular($limit);
    }

    /**
     * 创建动作
     */
    public function create(array $data): array
    {
        $exercise = $this->repository->create($data);

        // 清除缓存
        $this->cacheService->clearAllCache();

        return $exercise;
    }

    /**
     * 更新动作
     */
    public function update(int $id, array $data): bool
    {
        $result = $this->repository->update($id, $data);

        if ($result) {
            // 清除相关缓存
            $this->cacheService->clearExerciseCache($id);
        }

        return $result;
    }

    /**
     * 删除动作
     */
    public function delete(int $id): bool
    {
        $result = $this->repository->delete($id);

        if ($result) {
            $this->cacheService->clearExerciseCache($id);
        }

        return $result;
    }
}
