<?php

namespace App\Modules\Exercise\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Exercise\Services\ExerciseFilterService;
use Illuminate\Http\JsonResponse;

/**
 * Exercise Filter Controller
 * 
 * 动作筛选API控制器
 */
class ExerciseFilterController extends BaseController
{
    protected ExerciseFilterService $filterService;
    
    public function __construct(ExerciseFilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * 获取筛选选项
     * 
     * 添加HTTP缓存头：24小时缓存
     */
    public function options(): JsonResponse
    {
        try {
            $options = $this->filterService->getFilterOptions();
            
            $response = $this->success($options, '获取筛选选项成功');
            
            // 添加HTTP缓存头（24小时 = 86400秒）
            $response->header('Cache-Control', 'public, max-age=86400');
            $response->header('Expires', gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
            
            return $response;
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取筛选选项');
        }
    }
}

