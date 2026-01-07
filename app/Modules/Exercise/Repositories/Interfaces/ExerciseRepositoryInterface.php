<?php

namespace App\Modules\Exercise\Repositories\Interfaces;

use App\Modules\Exercise\Models\Exercise;

/**
 * Exercise Repository Interface
 *
 * 动作库数据访问层接口
 * 
 * @version 2.0.0
 * @date 2025-11-02
 * @changes findById() 返回类型改为 ?Exercise 以适配Resource
 */
interface ExerciseRepositoryInterface
{
    /**
     * 根据ID查找动作
     *
     * @return Exercise|null 返回模型实例而非数组
     */
    public function findById(int $id): ?Exercise;

    /**
     * 分页获取动作列表
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /**
     * 搜索动作
     */
    public function search(string $keyword, int $page, int $perPage): array;

    /**
     * 获取筛选选项
     */
    public function getFilterOptions(): array;

    /**
     * 获取热门动作
     */
    public function getPopular(int $limit): array;

    /**
     * 创建动作
     */
    public function create(array $data): array;

    /**
     * 更新动作
     */
    public function update(int $id, array $data): bool;

    /**
     * 删除动作
     */
    public function delete(int $id): bool;

    /**
     * 批量创建
     */
    public function bulkCreate(array $data): bool;

    /**
     * 统计总数
     */
    public function count(array $filters = []): int;
}
