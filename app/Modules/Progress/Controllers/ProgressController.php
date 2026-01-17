<?php

namespace App\Modules\Progress\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Modules\Progress\Models\ProgressRecord;
use App\Modules\Progress\Models\FitnessGoal;
use App\Modules\Training\Models\TrainingSession;
use App\Modules\Training\Models\TrainingRecord;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Progress Controller
 * 
 * 进度追踪控制器
 * 提供进度记录、目标管理、趋势数据等API
 * 
 * @version 1.1.0
 * @date 2026-01-06
 * @updated 2026-01-17 - 修复API响应规范合规性
 */
class ProgressController extends BaseController
{
    /**
     * 获取进度概览
     * GET /api/progress/overview
     */
    public function overview(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $startDate = $request->input('start_date', now()->subMonths(3)->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));

            // 获取体重趋势
            $weightTrend = ProgressRecord::where('user_id', $user->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date')
                ->get(['date', 'weight', 'body_fat'])
                ->map(fn($r) => [
                    'date' => $r->date->format('Y-m-d'),
                    'weight' => (float) $r->weight,
                    'bodyFat' => $r->body_fat ? (float) $r->body_fat : null,
                ]);

            // 获取FFMI趋势
            $ffmiTrend = ProgressRecord::where('user_id', $user->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->whereNotNull('ffmi')
                ->orderBy('date')
                ->get(['date', 'ffmi', 'lean_body_mass'])
                ->map(fn($r) => [
                    'date' => $r->date->format('Y-m-d'),
                    'ffmi' => (float) $r->ffmi,
                    'leanBodyMass' => (float) $r->lean_body_mass,
                ]);

            // 获取训练日历数据
            $trainingCalendar = $this->getTrainingCalendarData($user->id, now()->year, now()->month);

            // 获取活跃目标
            $activeGoals = FitnessGoal::where('user_id', $user->id)
                ->active()
                ->get()
                ->map(fn($g) => $this->formatGoal($g));

            // 获取最近记录
            $recentRecords = ProgressRecord::where('user_id', $user->id)
                ->orderBy('date', 'desc')
                ->limit(10)
                ->get()
                ->map(fn($r) => $this->formatRecord($r));

            // 计算统计数据
            $stats = $this->calculateStats($user->id);

            return $this->success([
                'weightTrend' => $weightTrend,
                'ffmiTrend' => $ffmiTrend,
                'trainingCalendar' => $trainingCalendar,
                'activeGoals' => $activeGoals,
                'recentRecords' => $recentRecords,
                'stats' => $stats,
            ], '获取进度概览成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取进度概览');
        }
    }

    /**
     * 获取进度记录列表
     * GET /api/progress/records
     */
    public function records(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $perPage = $request->input('per_page', 20);

            $query = ProgressRecord::where('user_id', $user->id)
                ->orderBy('date', 'desc');

            if ($startDate) {
                $query->where('date', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('date', '<=', $endDate);
            }

            $records = $query->paginate($perPage);

            return $this->success([
                'data' => $records->items()->map(fn($r) => $this->formatRecord($r)),
                'total' => $records->total(),
                'currentPage' => $records->currentPage(),
                'lastPage' => $records->lastPage(),
            ], '获取进度记录成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取进度记录');
        }
    }

    /**
     * 创建进度记录
     * POST /api/progress/records
     */
    public function createRecord(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'weight' => 'required|numeric|min:20|max:300',
                'body_fat' => 'nullable|numeric|min:1|max:60',
                'measurements' => 'nullable|array',
                'measurements.chest' => 'nullable|numeric|min:50|max:200',
                'measurements.waist' => 'nullable|numeric|min:40|max:200',
                'measurements.hips' => 'nullable|numeric|min:50|max:200',
                'measurements.arms' => 'nullable|numeric|min:20|max:60',
                'measurements.thighs' => 'nullable|numeric|min:30|max:100',
                'notes' => 'nullable|string|max:500',
            ]);

            $user = $request->user();

            // 检查是否已有当天记录
            $existing = ProgressRecord::where('user_id', $user->id)
                ->where('date', $request->date)
                ->first();

            if ($existing) {
                // 更新现有记录
                $existing->update([
                    'weight' => $request->weight,
                    'body_fat' => $request->body_fat,
                    'measurements' => $request->measurements,
                    'notes' => $request->notes,
                ]);
                $record = $existing->fresh();
            } else {
                // 创建新记录
                $record = ProgressRecord::create([
                    'user_id' => $user->id,
                    'date' => $request->date,
                    'weight' => $request->weight,
                    'body_fat' => $request->body_fat,
                    'measurements' => $request->measurements,
                    'notes' => $request->notes,
                ]);
            }

            // 同步更新用户档案的体重
            if ($user->userProfile) {
                $user->userProfile->update([
                    'weight' => $request->weight,
                    'body_fat_percentage' => $request->body_fat,
                ]);
            }

            return $this->success(
                $this->formatRecord($record),
                $existing ? '记录已更新' : '记录已创建'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '创建进度记录');
        }
    }

    /**
     * 获取单条进度记录
     * GET /api/progress/records/{id}
     */
    public function getRecord(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $record = ProgressRecord::where('user_id', $user->id)
                ->findOrFail($id);

            return $this->success($this->formatRecord($record), '获取记录成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取进度记录');
        }
    }

    /**
     * 更新进度记录
     * PUT /api/progress/records/{id}
     */
    public function updateRecord(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'weight' => 'nullable|numeric|min:20|max:300',
                'body_fat' => 'nullable|numeric|min:1|max:60',
                'measurements' => 'nullable|array',
                'notes' => 'nullable|string|max:500',
            ]);

            $user = $request->user();
            $record = ProgressRecord::where('user_id', $user->id)
                ->findOrFail($id);

            $record->update($request->only(['weight', 'body_fat', 'measurements', 'notes']));

            return $this->success($this->formatRecord($record->fresh()), '记录已更新');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '更新进度记录');
        }
    }

    /**
     * 删除进度记录
     * DELETE /api/progress/records/{id}
     */
    public function deleteRecord(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $record = ProgressRecord::where('user_id', $user->id)
                ->findOrFail($id);

            $record->delete();

            return $this->success(null, '记录已删除');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '删除进度记录');
        }
    }

    /**
     * 获取目标列表
     * GET /api/progress/goals
     */
    public function goals(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $status = $request->input('status', 'all');

            $query = FitnessGoal::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            $goals = $query->get()->map(fn($g) => $this->formatGoal($g));

            return $this->success($goals, '获取目标列表成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取目标列表');
        }
    }

    /**
     * 创建目标
     * POST /api/progress/goals
     */
    public function createGoal(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|in:weight,body_fat,muscle_mass,strength,custom',
                'name' => 'required|string|max:100',
                'target_value' => 'required|numeric',
                'current_value' => 'required|numeric',
                'unit' => 'required|string|max:20',
                'target_date' => 'nullable|date|after:today',
            ]);

            $user = $request->user();

            $goal = FitnessGoal::create([
                'user_id' => $user->id,
                'type' => $request->type,
                'name' => $request->name,
                'target_value' => $request->target_value,
                'current_value' => $request->current_value,
                'start_value' => $request->current_value,
                'unit' => $request->unit,
                'start_date' => now()->format('Y-m-d'),
                'target_date' => $request->target_date,
                'status' => 'active',
            ]);

            return $this->success($this->formatGoal($goal), '目标已创建');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '创建目标');
        }
    }

    /**
     * 更新目标
     * PUT /api/progress/goals/{id}
     */
    public function updateGoal(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'current_value' => 'nullable|numeric',
                'target_value' => 'nullable|numeric',
                'target_date' => 'nullable|date',
                'status' => 'nullable|in:active,completed,abandoned',
            ]);

            $user = $request->user();
            $goal = FitnessGoal::where('user_id', $user->id)
                ->findOrFail($id);

            $updateData = $request->only(['current_value', 'target_value', 'target_date', 'status']);

            // 如果状态变为completed，记录完成日期
            if (isset($updateData['status']) && $updateData['status'] === 'completed') {
                $updateData['completed_at'] = now()->format('Y-m-d');
            }

            $goal->update($updateData);

            return $this->success($this->formatGoal($goal->fresh()), '目标已更新');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '更新目标');
        }
    }

    /**
     * 删除目标
     * DELETE /api/progress/goals/{id}
     */
    public function deleteGoal(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $goal = FitnessGoal::where('user_id', $user->id)
                ->findOrFail($id);

            $goal->delete();

            return $this->success(null, '目标已删除');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '删除目标');
        }
    }

    /**
     * 获取训练日历数据
     * GET /api/progress/calendar
     */
    public function calendar(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $year = $request->input('year', now()->year);
            $month = $request->input('month', now()->month);

            $data = $this->getTrainingCalendarData($user->id, $year, $month);

            return $this->success($data, '获取日历数据成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取训练日历');
        }
    }

    /**
     * 获取体重趋势
     * GET /api/progress/trends/weight
     */
    public function weightTrend(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $startDate = $request->input('start_date', now()->subMonths(3)->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));

            $trend = ProgressRecord::where('user_id', $user->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date')
                ->get(['date', 'weight', 'body_fat'])
                ->map(fn($r) => [
                    'date' => $r->date->format('Y-m-d'),
                    'weight' => (float) $r->weight,
                    'bodyFat' => $r->body_fat ? (float) $r->body_fat : null,
                ]);

            return $this->success($trend, '获取体重趋势成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取体重趋势');
        }
    }

    /**
     * 获取FFMI趋势
     * GET /api/progress/trends/ffmi
     */
    public function ffmiTrend(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $startDate = $request->input('start_date', now()->subMonths(3)->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));

            $trend = ProgressRecord::where('user_id', $user->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->whereNotNull('ffmi')
                ->orderBy('date')
                ->get(['date', 'ffmi', 'lean_body_mass'])
                ->map(fn($r) => [
                    'date' => $r->date->format('Y-m-d'),
                    'ffmi' => (float) $r->ffmi,
                    'leanBodyMass' => (float) $r->lean_body_mass,
                ]);

            return $this->success($trend, '获取FFMI趋势成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取FFMI趋势');
        }
    }

    // ==================== 私有方法 ====================

    /**
     * 格式化进度记录
     */
    private function formatRecord(ProgressRecord $record): array
    {
        return [
            'id' => $record->id,
            'userId' => $record->user_id,
            'date' => $record->date->format('Y-m-d'),
            'weight' => (float) $record->weight,
            'bodyFat' => $record->body_fat ? (float) $record->body_fat : null,
            'ffmi' => $record->ffmi ? (float) $record->ffmi : null,
            'leanBodyMass' => $record->lean_body_mass ? (float) $record->lean_body_mass : null,
            'measurements' => $record->measurements,
            'photos' => $record->photos,
            'notes' => $record->notes,
            'createdAt' => $record->created_at->toISOString(),
            'updatedAt' => $record->updated_at->toISOString(),
        ];
    }

    /**
     * 格式化目标
     */
    private function formatGoal(FitnessGoal $goal): array
    {
        return [
            'id' => $goal->id,
            'userId' => $goal->user_id,
            'type' => $goal->type,
            'name' => $goal->name,
            'targetValue' => (float) $goal->target_value,
            'currentValue' => (float) $goal->current_value,
            'startValue' => (float) $goal->start_value,
            'unit' => $goal->unit,
            'startDate' => $goal->start_date->format('Y-m-d'),
            'targetDate' => $goal->target_date?->format('Y-m-d'),
            'completedAt' => $goal->completed_at?->format('Y-m-d'),
            'status' => $goal->status,
            'progress' => $goal->progress,
            'estimatedCompletion' => $goal->estimated_completion,
            'createdAt' => $goal->created_at->toISOString(),
            'updatedAt' => $goal->updated_at->toISOString(),
        ];
    }

    /**
     * 获取训练日历数据
     */
    private function getTrainingCalendarData(int $userId, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // 获取该月所有训练会话
        $sessions = TrainingSession::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->with('records')
            ->get();

        // 按日期分组
        $sessionsByDate = $sessions->groupBy(fn($s) => $s->completed_at->format('Y-m-d'));

        // 生成日历数据
        $calendar = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateStr = $current->format('Y-m-d');
            $daySessions = $sessionsByDate->get($dateStr, collect());

            $totalVolume = 0;
            foreach ($daySessions as $session) {
                foreach ($session->records as $record) {
                    $totalVolume += ($record->weight ?? 0) * ($record->reps ?? 0);
                }
            }

            $calendar[] = [
                'date' => $dateStr,
                'hasTraining' => $daySessions->isNotEmpty(),
                'sessionCount' => $daySessions->count(),
                'totalVolume' => round($totalVolume, 0),
            ];

            $current->addDay();
        }

        return $calendar;
    }

    /**
     * 计算统计数据
     */
    private function calculateStats(int $userId): array
    {
        // 获取最新进度记录
        $latestRecord = ProgressRecord::where('user_id', $userId)
            ->orderBy('date', 'desc')
            ->first();

        // 获取第一条记录（用于计算变化）
        $firstRecord = ProgressRecord::where('user_id', $userId)
            ->orderBy('date', 'asc')
            ->first();

        // 计算本月训练天数
        $thisMonth = TrainingSession::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->distinct('completed_at')
            ->count(DB::raw('DATE(completed_at)'));

        // 计算上月训练天数
        $lastMonth = TrainingSession::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->subMonth()->month)
            ->whereYear('completed_at', now()->subMonth()->year)
            ->distinct('completed_at')
            ->count(DB::raw('DATE(completed_at)'));

        // 计算总训练量（本月）
        $totalVolume = TrainingRecord::whereHas('session', function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->where('status', 'completed')
                ->whereMonth('completed_at', now()->month)
                ->whereYear('completed_at', now()->year);
        })->sum(DB::raw('COALESCE(weight, 0) * COALESCE(reps, 0)'));

        // 计算体重变化
        $weightChange = 0;
        if ($latestRecord && $firstRecord) {
            $weightChange = $latestRecord->weight - $firstRecord->weight;
        }

        // 计算FFMI变化
        $ffmiChange = null;
        if ($latestRecord && $firstRecord && $latestRecord->ffmi && $firstRecord->ffmi) {
            $ffmiChange = $latestRecord->ffmi - $firstRecord->ffmi;
        }

        return [
            'totalVolume' => round($totalVolume, 0),
            'trainingDaysThisMonth' => $thisMonth,
            'trainingDaysLastMonth' => $lastMonth,
            'currentWeight' => $latestRecord ? (float) $latestRecord->weight : 0,
            'weightChange' => round($weightChange, 1),
            'currentBodyFat' => $latestRecord?->body_fat ? (float) $latestRecord->body_fat : null,
            'bodyFatChange' => null, // TODO: 计算体脂变化
            'currentFFMI' => $latestRecord?->ffmi ? (float) $latestRecord->ffmi : null,
            'ffmiChange' => $ffmiChange ? round($ffmiChange, 2) : null,
            'totalRecords' => ProgressRecord::where('user_id', $userId)->count(),
        ];
    }
}
