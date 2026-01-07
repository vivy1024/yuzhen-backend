<?php

namespace App\Modules\Training\Repositories;

use App\Modules\Training\Repositories\Interfaces\TrainingPlanRepositoryInterface;
use App\Modules\Training\Models\TrainingPlan;
use App\Infrastructure\Database\Repositories\BaseRepository;

/**
 * Training Plan Repository
 * 
 * 训练计划数据访问层实现
 */
class TrainingPlanRepository extends BaseRepository implements TrainingPlanRepositoryInterface
{
    protected $model = TrainingPlan::class;

    public function findById(int $id): ?array
    {
        $plan = TrainingPlan::find($id);
        return $plan ? $plan->toArray() : null;
    }

    public function findByIdWithRelations(int $id, array $relations = []): ?array
    {
        $plan = TrainingPlan::with($relations)->find($id);
        return $plan ? $plan->toArray() : null;
    }

    public function findWhere(array $where, array $columns = ['*']): array
    {
        $query = TrainingPlan::query();
        
        foreach ($where as $condition) {
            if (count($condition) === 3) {
                $query->where($condition[0], $condition[1], $condition[2]);
            }
        }
        
        return $query->get($columns)->toArray();
    }

    public function getUserPlans(int $userId, array $filters = []): array
    {
        $query = TrainingPlan::where('user_id', $userId);
        
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        
        if (isset($filters['goal'])) {
            $query->where('goal', $filters['goal']);
        }
        
        return $query->orderBy('created_at', 'desc')->get()->toArray();
    }

    public function create(array $data): array
    {
        $plan = TrainingPlan::create($data);
        return $plan->toArray();
    }

    public function update(int $id, array $data): bool
    {
        return TrainingPlan::where('id', $id)->update($data) > 0;
    }

    public function delete(int $id): bool
    {
        return TrainingPlan::destroy($id) > 0;
    }

    /**
     * 创建训练计划动作关联
     */
    public function createExercise(array $data): array
    {
        $id = \DB::table('training_plan_exercises')->insertGetId($data);
        return array_merge(['id' => $id], $data);
    }

    /**
     * 获取训练计划的所有动作
     */
    public function getPlanExercises(int $planId): array
    {
        return \DB::table('training_plan_exercises')
            ->where('plan_id', $planId)
            ->orderBy('order_index')
            ->get()
            ->toArray();
    }
}

