<?php

namespace App\Modules\Training\Services;

use App\Modules\Training\Repositories\Interfaces\TrainingSessionRepositoryInterface;
use App\Modules\Training\Events\WorkoutCompleted;
use Illuminate\Support\Facades\Event;

/**
 * Training Session Service
 * 
 * 训练会话服务层
 */
class TrainingSessionService
{
    protected TrainingSessionRepositoryInterface $repository;
    
    public function __construct(TrainingSessionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * 获取训练会话详情
     */
    public function getDetail(int $id): ?array
    {
        return $this->repository->findByIdWithRelations($id, ['records', 'records.exercise']);
    }

    /**
     * 创建训练会话
     */
    public function create(int $planId, array $data): array
    {
        $data['training_plan_id'] = $planId;
        $data['status'] = 'pending';
        
        return $this->repository->create($data);
    }

    /**
     * 开始训练会话
     */
    public function start(int $id): bool
    {
        return $this->repository->update($id, [
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    /**
     * 完成训练会话
     */
    public function complete(int $id, array $data = []): bool
    {
        $updateData = [
            'status' => 'completed',
            'completed_at' => now(),
            'actual_duration' => $data['actual_duration'] ?? null,
            'calories_burned' => $data['calories_burned'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
        
        $result = $this->repository->update($id, $updateData);
        
        if ($result) {
            $session = $this->repository->findById($id);
            Event::dispatch(new WorkoutCompleted($session));
        }
        
        return $result;
    }

    /**
     * 添加训练记录
     */
    public function addRecord(int $sessionId, array $recordData): array
    {
        return $this->repository->addRecord($sessionId, $recordData);
    }

    /**
     * 获取会话的所有记录
     */
    public function getRecords(int $sessionId): array
    {
        return $this->repository->getSessionRecords($sessionId);
    }
}

