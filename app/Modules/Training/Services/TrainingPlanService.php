<?php

namespace App\Modules\Training\Services;

use App\Modules\Training\Repositories\Interfaces\TrainingPlanRepositoryInterface;
use App\Modules\Training\Events\TrainingPlanCreated;
use App\Modules\Training\Events\TrainingPlanCompleted;
use Illuminate\Support\Facades\Event;

/**
 * Training Plan Service
 * 
 * 训练计划服务层
 */
class TrainingPlanService
{
    protected TrainingPlanRepositoryInterface $repository;
    
    public function __construct(TrainingPlanRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * 获取用户的训练计划列表
     */
    public function getUserPlans(int $userId, array $filters = []): array
    {
        return $this->repository->getUserPlans($userId, $filters);
    }

    /**
     * 获取训练计划详情
     */
    public function getDetail(int $id): ?array
    {
        return $this->repository->findByIdWithRelations($id, ['sessions', 'progress']);
    }

    /**
     * 创建训练计划
     */
    public function create(int $userId, array $data): array
    {
        $data['user_id'] = $userId;
        $data['is_active'] = true;
        $data['started_at'] = now();

        $plan = $this->repository->create($data);

        // 触发创建事件
        Event::dispatch(new TrainingPlanCreated($plan));

        return $plan;
    }

    /**
     * 从AI对话创建训练计划
     */
    public function createFromAI(int $userId, array $data): array
    {
        // 准备基础数据
        $planData = [
            'user_id' => $userId,
            'name' => $data['name'] ?? 'AI定制训练计划',
            'description' => $data['description'] ?? '',
            'goal' => $data['goal'] ?? 'general_fitness',
            'frequency' => $data['frequency'] ?? 3,
            'duration_weeks' => $data['duration'] ?? 4,
            'difficulty_level' => $data['difficulty_level'] ?? 'beginner',
            'is_active' => false, // AI创建的计划默认不激活，用户需要手动激活
            'type' => 'ai_generated', // 标记来源类型
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // 创建训练计划
        $plan = $this->repository->create($planData);

        // 如果包含练习数据，创建训练计划练习关联
        if (!empty($data['exercises']) && is_array($data['exercises'])) {
            $this->attachExercises($plan['id'], $data['exercises']);
        }

        // 触发创建事件
        Event::dispatch(new TrainingPlanCreated($plan));

        return $plan;
    }

    /**
     * 为训练计划附加练习
     * 智能匹配数据库中的动作，无需硬编码映射
     */
    private function attachExercises(int $planId, array $exercises): void
    {
        foreach ($exercises as $exercise) {
            // 智能匹配数据库中的动作
            $matchedExercise = $this->findExerciseByName($exercise['exercise_name'] ?? '');

            $exerciseData = [
                'plan_id' => $planId,
                'exercise_id' => $matchedExercise['id'] ?? null,
                'exercise_name' => $exercise['exercise_name'] ?? '',
                'sets' => $exercise['sets'] ?? 3,
                'reps' => $exercise['reps'] ?? '8-12',
                'weight' => $exercise['weight'] ?? '',
                'rest_time' => $exercise['rest_time'] ?? '60s',
                'notes' => $exercise['notes'] ?? '',
                'order_index' => $exercise['order_index'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->repository->createExercise($exerciseData);
        }
    }

    /**
     * 根据动作名称智能匹配数据库中的动作
     */
    private function findExerciseByName(string $exerciseName): ?array
    {
        if (empty($exerciseName)) {
            return null;
        }

        // 首先尝试精确匹配中文名称
        $exactMatch = \App\Modules\Exercise\Models\Exercise::where('name_zh', $exerciseName)
            ->orWhere('name', $exerciseName)
            ->first();

        if ($exactMatch) {
            return $exactMatch->toArray();
        }

        // 尝试模糊匹配
        $fuzzyMatches = \App\Modules\Exercise\Models\Exercise::where('name_zh', 'like', '%' . $exerciseName . '%')
            ->orWhere('name', 'like', '%' . $exerciseName . '%')
            ->limit(3)
            ->get();

        if ($fuzzyMatches->isNotEmpty()) {
            return $fuzzyMatches->first()->toArray();
        }

        // 如果都没有匹配，返回null，让系统知道这是一个未识别的动作
        return null;
    }

    /**
     * 更新训练计划
     */
    public function update(int $id, array $data): ?array
    {
        $result = $this->repository->update($id, $data);
        
        if (!$result) {
            return null;
        }
        
        return $this->repository->findById($id);
    }

    /**
     * 删除训练计划
     */
    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * 激活训练计划
     */
    public function activate(int $id): ?array
    {
        $result = $this->repository->update($id, [
            'is_active' => true,
            'started_at' => now(),
        ]);
        
        if (!$result) {
            return null;
        }
        
        return $this->repository->findById($id);
    }

    /**
     * 停用用户的所有训练计划
     */
    public function deactivateUserPlans(int $userId): bool
    {
        try {
            $activePlans = $this->repository->findWhere([
                ['user_id', '=', $userId],
                ['is_active', '=', true],
            ]);
            
            foreach ($activePlans as $plan) {
                $this->repository->update($plan['id'], ['is_active' => false]);
            }
            
            return true;
        } catch (\Exception $e) {
            \Log::error('停用用户训练计划失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * 标记训练计划为已完成
     */
    public function complete(int $id): bool
    {
        $result = $this->repository->update($id, [
            'is_active' => false,
            'completed_at' => now(),
        ]);
        
        if ($result) {
            $plan = $this->repository->findById($id);
            Event::dispatch(new TrainingPlanCompleted($plan));
        }
        
        return $result;
    }

    /**
     * 获取用户的活跃计划
     */
    public function getActivePlan(int $userId): ?array
    {
        return $this->repository->findWhere([
            ['user_id', '=', $userId],
            ['is_active', '=', true],
        ])[0] ?? null;
    }

    /**
     * 获取推荐训练计划
     */
    public function getRecommended(int $userId, array $preferences = []): array
    {
        // TODO: 实现基于用户偏好的推荐算法
        return $this->repository->findWhere([
            ['is_active', '=', false],
        ]);
    }

    /**
     * 获取训练计划进度统计
     * 
     * Requirements: 5.2, 5.3 - 训练完成进度更新和历史完成率
     * 
     * @param int $id 计划ID
     * @return array 进度统计数据
     */
    public function getPlanProgressStats(int $id): array
    {
        $plan = $this->repository->findById($id);
        
        if (!$plan) {
            throw new \Exception('训练计划不存在');
        }

        // 获取关联的训练日志
        $logs = \App\Models\TrainingLog::where('training_plan_id', $id)
            ->orderBy('session_date', 'asc')
            ->get();

        // 计算统计数据
        $totalPlannedSessions = $plan['total_sessions'] ?? 0;
        $completedSessions = $logs->count();
        $completionRate = $totalPlannedSessions > 0 
            ? round(($completedSessions / $totalPlannedSessions) * 100, 1) 
            : 0;

        // 计算平均完成率和RPE
        $avgSessionCompletionRate = $logs->avg('completion_rate') ?? 0;
        $avgRpe = $logs->whereNotNull('avg_rpe')->avg('avg_rpe') ?? 0;

        // 按周统计完成率
        $weeklyStats = $logs->groupBy('plan_week')->map(function ($weekLogs, $week) {
            return [
                'week' => (int) $week,
                'rate' => round($weekLogs->avg('completion_rate') * 100, 1),
            ];
        })->values()->toArray();

        // 计算连续训练天数
        $streakDays = $this->calculateStreakDays($logs);

        // 最后训练日期
        $lastTrainingDate = $logs->max('session_date');

        return [
            'total_planned_sessions' => $totalPlannedSessions,
            'completed_sessions' => $completedSessions,
            'completion_rate' => $completionRate,
            'avg_session_completion_rate' => round($avgSessionCompletionRate * 100, 1),
            'avg_rpe' => round($avgRpe, 1),
            'weekly_completion_rates' => $weeklyStats,
            'streak_days' => $streakDays,
            'last_training_date' => $lastTrainingDate?->format('Y-m-d'),
        ];
    }

    /**
     * 计算连续训练天数
     * 
     * @param \Illuminate\Support\Collection $logs 训练日志集合
     * @return int 连续训练天数
     */
    private function calculateStreakDays($logs): int
    {
        if ($logs->isEmpty()) {
            return 0;
        }

        $dates = $logs->pluck('session_date')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        if (empty($dates)) {
            return 0;
        }

        // 从最近的日期开始计算连续天数
        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');
        $lastDate = end($dates);

        // 如果最后训练日期不是今天或昨天，连续天数为0
        if ($lastDate !== $today && $lastDate !== $yesterday) {
            return 0;
        }

        $streak = 1;
        $currentDate = \Carbon\Carbon::parse($lastDate);

        for ($i = count($dates) - 2; $i >= 0; $i--) {
            $prevDate = \Carbon\Carbon::parse($dates[$i]);
            $diff = $currentDate->diffInDays($prevDate);

            if ($diff === 1) {
                $streak++;
                $currentDate = $prevDate;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * 获取训练计划关联的训练日志
     * 
     * Requirements: 5.1 - 计划与训练记录关联
     * 
     * @param int $id 计划ID
     * @return array 训练日志列表
     */
    public function getPlanTrainingLogs(int $id): array
    {
        $logs = \App\Models\TrainingLog::where('training_plan_id', $id)
            ->orderBy('session_date', 'desc')
            ->get()
            ->toArray();

        return $logs;
    }
}

