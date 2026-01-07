<?php

namespace App\Modules\Training\Repositories\Interfaces;

/**
 * Training Plan Repository Interface
 * 
 * 训练计划数据访问层接口
 */
interface TrainingPlanRepositoryInterface
{
    public function findById(int $id): ?array;
    
    public function findByIdWithRelations(int $id, array $relations = []): ?array;
    
    public function findWhere(array $where): array;
    
    public function getUserPlans(int $userId, array $filters = []): array;
    
    public function create(array $data): array;
    
    public function update(int $id, array $data): bool;
    
    public function delete(int $id): bool;
}

