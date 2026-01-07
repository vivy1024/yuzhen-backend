<?php

namespace App\Modules\Training\Repositories\Interfaces;

/**
 * Training Session Repository Interface
 * 
 * 训练会话数据访问层接口
 */
interface TrainingSessionRepositoryInterface
{
    public function findById(int $id): ?array;
    
    public function findByIdWithRelations(int $id, array $relations = []): ?array;
    
    public function create(array $data): array;
    
    public function update(int $id, array $data): bool;
    
    public function delete(int $id): bool;
    
    public function addRecord(int $sessionId, array $recordData): array;
    
    public function getSessionRecords(int $sessionId): array;
}

