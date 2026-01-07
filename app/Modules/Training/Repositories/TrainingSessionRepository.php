<?php

namespace App\Modules\Training\Repositories;

use App\Modules\Training\Repositories\Interfaces\TrainingSessionRepositoryInterface;
use App\Modules\Training\Models\TrainingSession;
use App\Modules\Training\Models\TrainingRecord;
use App\Infrastructure\Database\Repositories\BaseRepository;

/**
 * Training Session Repository
 * 
 * 训练会话数据访问层实现
 */
class TrainingSessionRepository extends BaseRepository implements TrainingSessionRepositoryInterface
{
    protected $model = TrainingSession::class;

    public function findById(int $id): ?array
    {
        $session = TrainingSession::find($id);
        return $session ? $session->toArray() : null;
    }

    public function findByIdWithRelations(int $id, array $relations = []): ?array
    {
        $session = TrainingSession::with($relations)->find($id);
        return $session ? $session->toArray() : null;
    }

    public function create(array $data): array
    {
        $session = TrainingSession::create($data);
        return $session->toArray();
    }

    public function update(int $id, array $data): bool
    {
        return TrainingSession::where('id', $id)->update($data) > 0;
    }

    public function delete(int $id): bool
    {
        return TrainingSession::destroy($id) > 0;
    }

    public function addRecord(int $sessionId, array $recordData): array
    {
        $recordData['training_session_id'] = $sessionId;
        $record = TrainingRecord::create($recordData);
        return $record->toArray();
    }

    public function getSessionRecords(int $sessionId): array
    {
        return TrainingRecord::where('training_session_id', $sessionId)
            ->with('exercise')
            ->orderBy('set_number')
            ->get()
            ->toArray();
    }
}

