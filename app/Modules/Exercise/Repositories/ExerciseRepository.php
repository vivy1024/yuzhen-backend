<?php

namespace App\Modules\Exercise\Repositories;

use App\Modules\Exercise\Repositories\Interfaces\ExerciseRepositoryInterface;
use App\Modules\Exercise\Models\Exercise;
use App\Infrastructure\Database\Repositories\BaseRepository;

/**
 * Exercise Repository
 * 
 * 动作库数据访问层实现
 * 
 * @version 2.0.2
 * @date 2025-11-02
 * @changes 修复findById返回类型（返回模型而非数组）
 */
class ExerciseRepository extends BaseRepository implements ExerciseRepositoryInterface
{
    protected $model = Exercise::class;

    /**
     * 根据ID查找动作
     * 
     * @return Exercise|null 返回模型实例而非数组
     */
    public function findById(int $id): ?Exercise
    {
        return Exercise::with(['media'])->find($id);
    }

    /**
     * 分页获取动作列表
     * 
     * @version 2.0.3 修复字段名：使用 _zh/_en 后缀字段
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $query = Exercise::query();
        
        // ✅ 搜索关键词
        if (!empty($filters['query'])) {
            $keyword = $filters['query'];
            $query->where(function ($q) use ($keyword) {
                $q->where('name_en', 'like', "%{$keyword}%")
                  ->orWhere('name_zh', 'like', "%{$keyword}%")
                  ->orWhere('description_zh', 'like', "%{$keyword}%")
                  ->orWhereRaw("JSON_SEARCH(muscles_primary_zh, 'one', ?) IS NOT NULL", ["%{$keyword}%"]);
            });
        }
        
        // ✅ 肌群筛选（使用标准数组字段）
        if (!empty($filters['muscle'])) {
            $query->where(function($q) use ($filters) {
                // 使用 JSON_CONTAINS 查询数组字段
                $q->whereRaw("JSON_CONTAINS(muscles_primary_zh, ?)", [json_encode($filters['muscle'])])
                  ->orWhereRaw("JSON_SEARCH(muscles_primary_zh, 'one', ?) IS NOT NULL", ["%{$filters['muscle']}%"]);
            });
        }
        
        // ✅ 器械筛选（使用中文字段，支持多选）
        if (!empty($filters['equipment'])) {
            $equipments = is_array($filters['equipment']) ? $filters['equipment'] : explode(',', $filters['equipment']);
            $query->where(function($q) use ($equipments) {
                foreach ($equipments as $equipment) {
                    $q->orWhere('equipment_zh', 'like', "%{$equipment}%");
                }
            });
        }
        
        // ✅ 难度筛选（使用英文字段，支持多选）
        if (!empty($filters['difficulty'])) {
            $difficulties = is_array($filters['difficulty']) ? $filters['difficulty'] : explode(',', $filters['difficulty']);
            $query->whereIn('difficulty_en', $difficulties);
        }
        
        // ✅ 力量类型筛选（使用英文字段）
        if (!empty($filters['force'])) {
            $forces = is_array($filters['force']) ? $filters['force'] : explode(',', $filters['force']);
            $query->whereIn('force_en', $forces);
        }
        
        // ✅ 机制类型筛选（使用英文字段）
        if (!empty($filters['mechanic'])) {
            $mechanics = is_array($filters['mechanic']) ? $filters['mechanic'] : explode(',', $filters['mechanic']);
            $query->whereIn('mechanic_en', $mechanics);
        }
        
        $paginator = $query->with(['media'])
            ->orderBy('view_count', 'desc')
            ->orderBy('name_zh')
            ->paginate($perPage, ['*'], 'page', $page);
        
        return [
            'rows' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    /**
     * 搜索动作
     */
    public function search(string $keyword, int $page, int $perPage): array
    {
        $query = Exercise::search($keyword);
        
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        
        return [
            'rows' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * 获取筛选选项
     */
    public function getFilterOptions(): array
    {
        return [
            'muscles' => $this->getUniqueValues('primary_muscle'),
            'equipment' => $this->getUniqueValues('equipment'),
            'difficulties' => $this->getUniqueValues('difficulty'),
            'grips' => $this->getGripsOptions(),
            'mechanics' => $this->getUniqueValues('mechanic_type'),
            'forces' => $this->getUniqueValues('force_type'),
            'kinetic_chains' => $this->getKineticChainOptions(),
            'safety_levels' => $this->getSafetyLevelOptions(),
        ];
    }

    /**
     * 获取热门动作
     */
    public function getPopular(int $limit): array
    {
        return Exercise::orderBy('view_count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 创建动作
     */
    public function create(array $data): array
    {
        $exercise = Exercise::create($data);
        return $exercise->toArray();
    }

    /**
     * 更新动作
     */
    public function update(int $id, array $data): bool
    {
        return Exercise::where('id', $id)->update($data) > 0;
    }

    /**
     * 删除动作
     */
    public function delete(int $id): bool
    {
        return Exercise::destroy($id) > 0;
    }

    /**
     * 批量创建
     */
    public function bulkCreate(array $data): bool
    {
        return Exercise::insert($data);
    }

    /**
     * 统计总数
     */
    public function count(array $filters = []): int
    {
        $query = Exercise::query();
        
        // 应用筛选
        if (!empty($filters['muscle'])) {
            $query->byMuscle($filters['muscle']);
        }
        
        return $query->count();
    }

    /**
     * 获取唯一值（用于筛选选项）
     * 
     * @version 2.1.0 使用标准数组字段 muscles_primary_zh
     */
    private function getUniqueValues(string $column): array
    {
        // 字段名映射：API字段 -> 数据库实际字段
        $columnMap = [
            'primary_muscle' => 'muscles_primary_zh',  // 使用标准数组字段
            'equipment' => 'equipment_zh',
            'difficulty' => 'difficulty_en',  // 难度用英文（用于查询）
            'mechanic_type' => 'mechanic_en',
            'force_type' => 'force_en',
        ];
        
        $dbColumn = $columnMap[$column] ?? $column;
        
        // 如果是数组字段（muscles_primary_zh），需要特殊处理
        if ($dbColumn === 'muscles_primary_zh') {
            return $this->getMuscleOptions();
        }
        
        $results = Exercise::whereNotNull($dbColumn)
            ->where($dbColumn, '!=', '')
            ->where($dbColumn, '!=', 'Unknown')
            ->where($dbColumn, '!=', 'unknown')
            ->where($dbColumn, '!=', '未知')
            ->selectRaw("$dbColumn as value, COUNT(*) as count")
            ->groupBy($dbColumn)
            ->orderBy('count', 'desc')
            ->get();
        
        // 额外过滤：确保不包含Unknown（双保险）
        $filtered = $results->filter(function ($item) {
            $value = $item->value;
            return !in_array($value, ['Unknown', 'unknown', '未知', '', null], true);
        })->map(function ($item) {
            return [
                'value' => $item->value,
                'label' => $item->value,
                'count' => $item->count,
            ];
        })->values()->toArray();
        
        return $filtered;
    }
    
    /**
     * 获取肌肉群选项（从JSON数组字段解析）
     * 
     * @version 2.1.0 新增方法，处理 muscles_primary_zh 数组字段
     */
    private function getMuscleOptions(): array
    {
        // 获取所有非空的 muscles_primary_zh JSON字段
        $musclesData = Exercise::whereNotNull('muscles_primary_zh')
            ->where('muscles_primary_zh', '!=', '')
            ->where('muscles_primary_zh', '!=', 'null')
            ->where('muscles_primary_zh', '!=', '[]')
            ->pluck('muscles_primary_zh');

        $musclesCount = [];
        
        foreach ($musclesData as $muscleJson) {
            // 处理JSON字符串或已经是数组的情况
            if (is_string($muscleJson)) {
                $muscleArray = json_decode($muscleJson, true);
            } else {
                $muscleArray = $muscleJson;
            }
            
            // 处理数组格式的肌肉数据
            if (is_array($muscleArray) && !empty($muscleArray)) {
                foreach ($muscleArray as $muscle) {
                    // 统计肌肉出现次数
                    if ($muscle && !empty(trim($muscle))) {
                        if (!isset($musclesCount[$muscle])) {
                            $musclesCount[$muscle] = 0;
                        }
                        $musclesCount[$muscle]++;
                    }
                }
            }
        }
        
        // 转换为筛选选项格式并排序
        $result = collect($musclesCount)->map(function ($count, $muscle) {
            return [
                'value' => $muscle,
                'label' => $muscle,
                'count' => $count,
            ];
        })->sortByDesc('count')->values()->toArray();
        
        return $result;
    }

    /**
     * 获取握法选项（从JSON字段解析）
     * 
     * @version 2.0.3 修复字段名：使用 grips_zh
     */
    private function getGripsOptions(): array
    {
        // 获取所有非空的grips_zh JSON字段
        $gripsData = Exercise::whereNotNull('grips_zh')
            ->where('grips_zh', '!=', '')
            ->where('grips_zh', '!=', 'null')
            ->where('grips_zh', '!=', '[]')
            ->pluck('grips_zh');

        $gripsCount = [];
        
        foreach ($gripsData as $gripJson) {
            // 处理JSON字符串或已经是数组的情况
            if (is_string($gripJson)) {
                $gripArray = json_decode($gripJson, true);
            } else {
                $gripArray = $gripJson;
            }
            
            // 处理数组格式的握法数据
            if (is_array($gripArray) && !empty($gripArray)) {
                foreach ($gripArray as $grip) {
                    $gripName = null;
                    
                    // 处理字符串格式："反手握"
                    if (is_string($grip)) {
                        $gripName = $grip;
                    }
                    
                    // 统计握法出现次数
                    if ($gripName && !empty(trim($gripName))) {
                        if (!isset($gripsCount[$gripName])) {
                            $gripsCount[$gripName] = 0;
                        }
                        $gripsCount[$gripName]++;
                    }
                }
            }
        }
        
        // 转换为筛选选项格式并排序
        $result = collect($gripsCount)->map(function ($count, $grip) {
            return [
                'value' => $grip,
                'label' => $grip,
                'count' => $count,
            ];
        })->sortByDesc('count')->values()->toArray();
        
        return $result;
    }

    /**
     * 获取运动链类型选项
     */
    private function getKineticChainOptions(): array
    {
        $map = [
            'open_chain' => '开链运动',
            'closed_chain' => '闭链运动',
            'mixed' => '混合运动',
        ];
        
        $results = Exercise::whereNotNull('kinetic_chain_type')
            ->where('kinetic_chain_type', '!=', '')
            ->selectRaw("kinetic_chain_type as value, COUNT(*) as count")
            ->groupBy('kinetic_chain_type')
            ->orderBy('count', 'desc')
            ->get();
        
        return $results->map(function ($item) use ($map) {
            return [
                'value' => $item->value,
                'label' => $map[$item->value] ?? $item->value,
                'count' => $item->count,
            ];
        })->toArray();
    }

    /**
     * 获取安全等级选项
     */
    private function getSafetyLevelOptions(): array
    {
        $map = [
            'LOW_RISK' => '低风险',
            'MODERATE_RISK' => '中等风险',
            'HIGH_RISK' => '高风险',
        ];
        
        $results = Exercise::whereNotNull('safety_level')
            ->where('safety_level', '!=', '')
            ->selectRaw("safety_level as value, COUNT(*) as count")
            ->groupBy('safety_level')
            ->orderBy('count', 'desc')
            ->get();
        
        return $results->map(function ($item) use ($map) {
            return [
                'value' => $item->value,
                'label' => $map[$item->value] ?? $item->value,
                'count' => $item->count,
            ];
        })->toArray();
    }
}
