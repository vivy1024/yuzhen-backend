<?php

namespace App\Modules\Help\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 帮助中心控制器
 * 
 * @version 1.1.0 - 2026-01-17: 修复API响应规范合规性
 */
class HelpController extends BaseController
{
    /**
     * 获取FAQ列表
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Faq::active()->ordered();

            // 按分类筛选
            if ($request->has('category') && $request->category) {
                $query->byCategory($request->category);
            }

            // 搜索
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('question', 'like', "%{$search}%")
                      ->orWhere('answer', 'like', "%{$search}%");
                });
            }

            $faqs = $query->get();

            return $this->success($faqs, '获取FAQ列表成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取FAQ列表');
        }
    }

    /**
     * 获取FAQ详情
     */
    public function show(int $id): JsonResponse
    {
        try {
            $faq = Faq::active()->find($id);

            if (!$faq) {
                return $this->fail('FAQ不存在', 404);
            }

            // 获取相关问题（同分类的其他问题）
            $related = Faq::active()
                ->byCategory($faq->category)
                ->where('id', '!=', $faq->id)
                ->ordered()
                ->limit(5)
                ->get();

            return $this->success([
                'faq' => $faq,
                'related' => $related
            ], '获取FAQ详情成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取FAQ详情');
        }
    }

    /**
     * 提交FAQ反馈
     */
    public function feedback(Request $request, int $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'helpful' => 'required|boolean',
            ], [
                'helpful.required' => '请选择是否有帮助',
                'helpful.boolean' => '参数格式错误',
            ]);

            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 422);
            }

            $faq = Faq::active()->find($id);

            if (!$faq) {
                return $this->fail('FAQ不存在', 404);
            }

            if ($request->helpful) {
                $faq->incrementHelpful();
            } else {
                $faq->incrementNotHelpful();
            }

            return $this->success(null, '感谢您的反馈');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '提交FAQ反馈');
        }
    }

    /**
     * 获取分类列表
     */
    public function categories(): JsonResponse
    {
        try {
            return $this->success(Faq::getCategoryLabels(), '获取分类列表成功');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '获取分类列表');
        }
    }
}
