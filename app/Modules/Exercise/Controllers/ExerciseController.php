<?php

namespace App\Modules\Exercise\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Modules\Exercise\Services\ExerciseService;
use App\Modules\Exercise\Services\ExerciseFilterService;
use App\Modules\Exercise\Requests\FilterExerciseRequest;
use App\Modules\Exercise\Resources\ExerciseResource;
use App\Modules\Exercise\Resources\ExerciseDetailResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Exercise Controller
 * 
 * 动作库API控制器（微服务化版本）
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class ExerciseController extends BaseController
{
    protected ExerciseService $exerciseService;
    protected ExerciseFilterService $filterService;
    
    public function __construct(
        ExerciseService $exerciseService,
        ExerciseFilterService $filterService
    ) {
        $this->exerciseService = $exerciseService;
        $this->filterService = $filterService;
    }

    /**
     * 获取动作列表
     */
    public function index(FilterExerciseRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            
            $result = $this->exerciseService->getList($filters, $page, $perPage);
            
            return $this->page(
                ExerciseResource::collection($result['rows'])->resolve(),
                $result['total'],
                $result['page'],
                $result['per_page'],
                '获取动作列表成功'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取动作列表');
        }
    }

    /**
     * 获取动作详情
     */
    public function show(int $id): JsonResponse
    {
        try {
            $exercise = $this->exerciseService->getDetail($id);
            
            if (!$exercise) {
                return $this->fail('动作不存在', 404);
            }
            
            return $this->success(
                new ExerciseDetailResource($exercise),
                '获取动作详情成功'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取动作详情');
        }
    }

    /**
     * 搜索动作
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $keyword = $request->input('q', '');
            
            if (empty($keyword)) {
                return $this->fail('搜索关键词不能为空', 400);
            }
            
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            
            $result = $this->exerciseService->search($keyword, $page, $perPage);
            
            return $this->success([
                'keyword' => $keyword,
                'results' => ExerciseResource::collection($result['rows'])->resolve(),
                'total' => $result['total']
            ], '搜索完成');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '搜索动作');
        }
    }
}

