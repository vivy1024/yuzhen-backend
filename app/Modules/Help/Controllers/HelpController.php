<?php

namespace App\Modules\Help\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HelpController extends Controller
{
    /**
     * 获取FAQ列表
     */
    public function index(Request $request): JsonResponse
    {
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

        return response()->json([
            'code' => 200,
            'msg' => 'success',
            'data' => $faqs
        ]);
    }

    /**
     * 获取FAQ详情
     */
    public function show(int $id): JsonResponse
    {
        $faq = Faq::active()->find($id);

        if (!$faq) {
            return response()->json([
                'code' => 404,
                'msg' => 'FAQ不存在'
            ], 404);
        }

        // 获取相关问题（同分类的其他问题）
        $related = Faq::active()
            ->byCategory($faq->category)
            ->where('id', '!=', $faq->id)
            ->ordered()
            ->limit(5)
            ->get();

        return response()->json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'faq' => $faq,
                'related' => $related
            ]
        ]);
    }

    /**
     * 提交FAQ反馈
     */
    public function feedback(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'helpful' => 'required|boolean',
        ], [
            'helpful.required' => '请选择是否有帮助',
            'helpful.boolean' => '参数格式错误',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'msg' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $faq = Faq::active()->find($id);

        if (!$faq) {
            return response()->json([
                'code' => 404,
                'msg' => 'FAQ不存在'
            ], 404);
        }

        if ($request->helpful) {
            $faq->incrementHelpful();
        } else {
            $faq->incrementNotHelpful();
        }

        return response()->json([
            'code' => 200,
            'msg' => '感谢您的反馈'
        ]);
    }

    /**
     * 获取分类列表
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'code' => 200,
            'msg' => 'success',
            'data' => Faq::getCategoryLabels()
        ]);
    }
}
