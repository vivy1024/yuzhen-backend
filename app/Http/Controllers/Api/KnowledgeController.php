<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgeSearchRequest;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    /**
     * GET /api/knowledge — 知识列表（分页）
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 15), 50);

        $query = KnowledgeArticle::published()->with('category:id,name');

        if ($categoryId = $request->input('category_id')) {
            $query->byCategory((int) $categoryId);
        }
        if ($tag = $request->input('tag')) {
            $query->byTag($tag);
        }
        if ($difficulty = $request->input('difficulty')) {
            $query->where('difficulty', $difficulty);
        }

        $paginator = $query->select([
            'id', 'title', 'summary', 'category_id',
            'tags', 'source_book', 'difficulty', 'view_count', 'created_at',
        ])->latest()->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /api/knowledge/{id} — 知识详情
     */
    public function show(int $id): JsonResponse
    {
        $article = KnowledgeArticle::published()
            ->with(['category:id,name,slug', 'references'])
            ->findOrFail($id);

        // 原子递增，避免并发竞争
        KnowledgeArticle::where('id', $id)->increment('view_count');
        $article->view_count += 1;

        $related = KnowledgeArticle::published()
            ->byCategory($article->category_id)
            ->where('id', '!=', $id)
            ->select(['id', 'title', 'summary', 'difficulty', 'view_count'])
            ->inRandomOrder()
            ->limit(5)
            ->get();

        return response()->json([
            'data' => array_merge($article->toArray(), ['related' => $related]),
        ]);
    }

    /**
     * GET /api/knowledge/categories — 分类树
     */
    public function categories(): JsonResponse
    {
        $tree = KnowledgeCategory::tree();

        // 为每个分类附加文章数量
        $counts = KnowledgeArticle::published()
            ->selectRaw('category_id, count(*) as articles_count')
            ->groupBy('category_id')
            ->pluck('articles_count', 'category_id');

        $attachCount = function ($categories) use (&$attachCount, $counts) {
            foreach ($categories as $cat) {
                $cat->articles_count = $counts->get($cat->id, 0);
                if ($cat->relationLoaded('children')) {
                    $attachCount($cat->children);
                }
            }
        };
        $attachCount($tree);

        return response()->json(['data' => $tree]);
    }

    /**
     * GET /api/knowledge/cards — 知识卡片（随机）
     */
    public function cards(Request $request): JsonResponse
    {
        $count = min((int) $request->input('count', 10), 20);

        $query = KnowledgeArticle::published()->with('category:id,name');

        if ($categoryId = $request->input('category_id')) {
            $query->byCategory((int) $categoryId);
        }

        $articles = $query->select(['id', 'title', 'summary', 'category_id', 'tags'])
            ->inRandomOrder()
            ->limit($count)
            ->get();

        return response()->json(['data' => $articles]);
    }

    /**
     * GET /api/knowledge/search — 搜索
     */
    public function search(KnowledgeSearchRequest $request): JsonResponse
    {
        $q = $request->input('q');
        $perPage = min((int) $request->input('per_page', 15), 50);
        $like = '%' . $q . '%';

        $paginator = KnowledgeArticle::published()
            ->with('category:id,name')
            ->where(function ($query) use ($like) {
                $query->where('title', 'like', $like)
                      ->orWhere('summary', 'like', $like)
                      ->orWhere('content', 'like', $like);
            })
            ->select(['id', 'title', 'summary', 'category_id', 'tags', 'source_book', 'difficulty', 'view_count', 'created_at'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }
}
