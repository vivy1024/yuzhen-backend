<?php

namespace App\Modules\Exercise\Services;

use App\Modules\Exercise\Repositories\Interfaces\ExerciseRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Exercise Filter Service
 * 
 * 动作筛选服务
 */
class ExerciseFilterService
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
     * 获取筛选选项
     */
    public function getFilterOptions(): array
    {
        $cacheKey = $this->cacheService->generateFilterOptionsCacheKey();
        
        // 临时禁用缓存，直接从数据库获取数据
        // TODO: 调查生产环境缓存问题后恢复缓存
        $options = $this->repository->getFilterOptions();
        
        // 调试日志：记录原始数据（使用error级别确保输出）
        \Log::error('ExerciseFilterService: 原始筛选选项数据', [
            'grips_count' => count($options['grips'] ?? []),
            'mechanics_count' => count($options['mechanics'] ?? []),
            'forces_count' => count($options['forces'] ?? []),
            'grips_sample' => array_slice($options['grips'] ?? [], 0, 3),
            'mechanics_sample' => array_slice($options['mechanics'] ?? [], 0, 3),
            'forces_sample' => array_slice($options['forces'] ?? [], 0, 3),
        ]);
        
        // ✅ 修复：使用单数形式的键名，与前端 FilterOptions 类型匹配
        $formatted = [
            'muscle' => $this->formatOptions($options['muscles'] ?? []),
            'equipment' => $this->formatOptions($options['equipment'] ?? []),
            'difficulty' => $this->formatDifficulties($options['difficulties'] ?? []),
            'grip' => $this->formatOptions($options['grips'] ?? []),
            'mechanic' => $this->formatOptions($options['mechanics'] ?? []),
            'force' => $this->formatOptions($options['forces'] ?? []),
            'kinetic_chain' => $options['kinetic_chains'] ?? [],
            'safety_level' => $options['safety_levels'] ?? [],
        ];
        
        // 调试日志：记录格式化后的数据（使用error级别确保输出）
        \Log::error('ExerciseFilterService: 格式化后的筛选选项数据', [
            'grip_count' => count($formatted['grip']),
            'mechanic_count' => count($formatted['mechanic']),
            'force_count' => count($formatted['force']),
        ]);
        
        return $formatted;
    }

    /**
     * 格式化选项
     */
    private function formatOptions(array $options): array
    {
        // 如果为空数组，直接返回
        if (empty($options)) {
            return [];
        }
        
        return array_map(function ($option) {
            // 确保option是数组且包含必要的键
            if (!is_array($option)) {
                return null;
            }
            
            // 优先使用translateLabel进行翻译（确保所有值都经过翻译）
            // 如果Repository已经设置了中文label，translateLabel会返回原始值（不会覆盖）
            $value = $option['value'] ?? '';
            $translatedLabel = $this->translateLabel($value);
            
            // 如果translateLabel返回了翻译值（与原始值不同），使用翻译值；否则使用Repository设置的label
            $label = ($translatedLabel !== $value && !empty($translatedLabel)) 
                ? $translatedLabel 
                : ($option['label'] ?? $value);
            
            return [
                'value' => $value,
                'label' => $label,
                'count' => $option['count'] ?? 0,
            ];
        }, array_filter($options, function ($option) {
            // 过滤掉无效的选项
            return is_array($option) && isset($option['value']) && !empty($option['value']);
        }));
    }

    /**
     * 格式化难度选项
     */
    private function formatDifficulties(array $difficulties): array
    {
        $difficultyMap = [
            'beginner' => '初学者',
            'novice' => '零基础',
            'intermediate' => '中级',
            'advanced' => '高级',
        ];
        
        return array_map(function ($difficulty) use ($difficultyMap) {
            return [
                'value' => $difficulty['value'],
                'label' => $difficultyMap[strtolower($difficulty['value'])] ?? $difficulty['value'],
                'count' => $difficulty['count'],
            ];
        }, $difficulties);
    }

    /**
     * 翻译标签（简单实现，后续可扩展为i18n）
     */
    private function translateLabel(string $value): string
    {
        // 握法类型映射
        $gripMap = [
            'Underhand' => '反手握',
            'Overhand' => '正手握',
            'Neutral' => '中立握',
            'Mixed' => '正反握',
            'Hook' => '锁握',
            'False' => '假握',
            'None' => '无握法',
            'Rotating' => '旋转握',
            'Pinch Grip' => '捏握'
        ];
        
        // 力量类型映射
        $forceMap = [
            'Push' => '推力',
            'Pull' => '拉力',
            'Static' => '静力',
            'Dynamic' => '动力',
            'Hold' => '静力',
        ];
        
        // 机制类型映射
        $mechanicMap = [
            'Compound' => '复合训练',
            'Isolation' => '孤立训练',
        ];
        
        // 优先查找握法类型
        if (isset($gripMap[$value])) {
            return $gripMap[$value];
        }
        
        // 然后查找力量类型
        if (isset($forceMap[$value])) {
            return $forceMap[$value];
        }
        
        // 最后查找机制类型
        if (isset($mechanicMap[$value])) {
            return $mechanicMap[$value];
        }
        
        // 默认返回原始值（如果Repository已经设置了中文label，这里返回原始值，formatOptions会使用Repository的label）
        return $value;
    }
}