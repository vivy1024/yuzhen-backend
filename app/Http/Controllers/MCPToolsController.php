<?php

namespace App\Http\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MCP Tools Controller
 *
 * 代理前端的MCP工具请求到Meta-Learning MCO服务器
 *
 * 架构: 前端(9000) → 后端API网关(8000) → MCO服务器(8001)
 *
 * @version v1.1.0
 * @date 2026-01-17 (修复API响应规范合规性)
 * @created 2025-11-03
 */
class MCPToolsController extends BaseController
{
    /**
     * MCO服务器基础URL
     */
    private string $mcoBaseUrl;

    /**
     * 超时时间（毫秒）
     */
    private int $timeout = 60000; // AI生成可能需要较长时间

    public function __construct()
    {
        $this->mcoBaseUrl = env('MCO_BASE_URL', 'http://localhost:8001');
    }

    /**
     * 通用MCP工具调用
     *
     * POST /api/tools/execute
     *
     * 请求格式:
     * {
     *   "server": "fitness",
     *   "method": "search_exercises",
     *   "args": {...}
     * }
     *
     * 响应格式:
     * {
     *   "success": true,
     *   "data": {...},
     *   "timestamp": "2025-11-03T..."
     * }
     */
    public function executeGeneric(Request $request): JsonResponse
    {
        try {
            $server = $request->input('server');
            $method = $request->input('method');
            $args = $request->input('args', []);

            Log::info("MCP工具调用", [
                'server' => $server,
                'method' => $method,
                'args' => $args,
            ]);

            // 转发到MCO服务器
            $response = Http::timeout($this->timeout / 1000)
                ->post("{$this->mcoBaseUrl}/tools/execute", [
                    'server' => $server,
                    'method' => $method,
                    'args' => $args,
                ]);

            if ($response->successful()) {
                return $this->success($response->json());
            }

            Log::error("MCO调用失败", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->fail('MCO服务器响应失败', 500);

        } catch (\Exception $e) {
            return $this->handleException($e, 'MCP工具调用');
        }
    }

    /**
     * AI生成个性化训练计划
     *
     * POST /api/mcp/tools/design-personalized-program-v2
     *
     * 请求参数:
     * - age: 年龄
     * - weight: 体重(kg)
     * - height: 身高(cm)
     * - gender: 性别
     * - training_level: 训练水平
     * - goal: 目标
     * - equipment_access: 可用器械
     * - training_frequency: 训练频率
     * - session_duration: 单次时长
     */
    public function designPersonalizedProgram(Request $request): JsonResponse
    {
        try {
            $params = $request->all();

            Log::info("AI生成训练计划", ['params' => $params]);

            // 调用MCO服务器的聊天接口
            $response = Http::timeout($this->timeout / 1000)
                ->post("{$this->mcoBaseUrl}/chat", [
                    'message' => $this->buildProgramPrompt($params),
                    'user_id' => auth()->id() ?? 'guest',
                    'context' => [
                        'tool' => 'design-personalized-program-v2',
                        'params' => $params,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return $this->success(
                    $this->parseTrainingPlan($data['response']),
                    '生成成功'
                );
            }

            Log::error("MCO生成训练计划失败", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->fail('AI生成失败，请重试', 500);

        } catch (\Exception $e) {
            return $this->handleException($e, '生成训练计划');
        }
    }

    /**
     * AI计算训练重量推荐
     *
     * POST /api/mcp/tools/calculate-training-weights
     */
    public function calculateTrainingWeights(Request $request): JsonResponse
    {
        try {
            $params = $request->all();

            $response = Http::timeout(5)
                ->post("{$this->mcoBaseUrl}/chat", [
                    'message' => $this->buildWeightCalculationPrompt($params),
                    'user_id' => $params['user_id'] ?? auth()->id(),
                    'context' => ['tool' => 'calculate-training-weights'],
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return $this->success(
                    $this->parseWeightRecommendation($data['response']),
                    '计算成功'
                );
            }

            return $this->fail('计算失败', 500);

        } catch (\Exception $e) {
            return $this->handleException($e, '计算训练重量');
        }
    }

    /**
     * 推荐RPE范围
     *
     * POST /api/mcp/tools/recommend-rpe-range
     */
    public function recommendRPERange(Request $request): JsonResponse
    {
        try {
            $params = $request->all();

            $response = Http::timeout(5)
                ->post("{$this->mcoBaseUrl}/chat", [
                    'message' => $this->buildRPEPrompt($params),
                    'user_id' => $params['user_id'] ?? auth()->id(),
                    'context' => ['tool' => 'recommend-rpe-range'],
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return $this->success(
                    $this->parseRPERecommendation($data['response']),
                    '推荐成功'
                );
            }

            return $this->fail('推荐失败', 500);

        } catch (\Exception $e) {
            return $this->handleException($e, '推荐RPE范围');
        }
    }

    /**
     * 搜索动作
     *
     * POST /api/mcp/tools/search-exercises
     */
    public function searchExercises(Request $request): JsonResponse
    {
        try {
            $params = $request->all();

            // 直接使用数据库搜索（更快）
            $query = \App\Models\Exercise::query();

            if (!empty($params['query'])) {
                $query->where(function($q) use ($params) {
                    $q->where('name_zh', 'like', "%{$params['query']}%")
                      ->orWhere('name_en', 'like', "%{$params['query']}%");
                });
            }

            if (!empty($params['muscle_group'])) {
                $query->where('primary_muscle', $params['muscle_group']);
            }

            if (!empty($params['equipment_type'])) {
                $query->where('equipment', $params['equipment_type']);
            }

            if (!empty($params['difficulty'])) {
                $query->where('difficulty', $params['difficulty']);
            }

            $limit = $params['limit'] ?? 20;
            $exercises = $query->limit($limit)->get();

            return $this->success($exercises->map(function($ex) {
                return [
                    'id' => $ex->id,
                    'name' => $ex->name_zh,
                    'name_en' => $ex->name_en,
                    'description' => $ex->description_zh,
                    'muscle_group' => $ex->primary_muscle,
                    'secondary_muscles' => json_decode($ex->secondary_muscles ?? '[]', true),
                    'equipment' => $ex->equipment,
                    'difficulty' => $ex->difficulty,
                    'image_url' => $ex->image_url,
                    'video_url' => $ex->video_url,
                ];
            }), '搜索成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '搜索动作');
        }
    }

    // ============= 辅助方法 =============

    /**
     * 构建训练计划生成提示词
     */
    private function buildProgramPrompt(array $params): string
    {
        return "请根据以下用户信息生成一份个性化训练计划：\n\n" .
               "- 年龄: {$params['age']}岁\n" .
               "- 体重: {$params['weight']}kg\n" .
               "- 身高: {$params['height']}cm\n" .
               "- 性别: {$params['gender']}\n" .
               "- 训练水平: {$params['training_level']}\n" .
               "- 目标: {$params['goal']}\n" .
               "- 可用器械: " . implode(', ', $params['equipment_access'] ?? []) . "\n" .
               "- 训练频率: 每周{$params['training_frequency']}次\n" .
               "- 单次时长: {$params['session_duration']}分钟\n" .
               (isset($params['injuries']) ? "- 伤病史: {$params['injuries']}\n" : "") .
               "\n请生成包含动作、组数、次数、休息时间的详细计划。";
    }

    /**
     * 构建重量计算提示词
     */
    private function buildWeightCalculationPrompt(array $params): string
    {
        $history = $params['training_history'] ?? [];
        $historyText = '';

        if (!empty($history)) {
            $historyText = "历史训练记录:\n";
            foreach ($history as $record) {
                $historyText .= "- {$record['date']}: {$record['weight']}kg × {$record['reps']}次";
                if (isset($record['rpe'])) {
                    $historyText .= " (RPE: {$record['rpe']})";
                }
                $historyText .= "\n";
            }
        }

        return "请推荐训练重量：\n\n" .
               "- 动作ID: {$params['exercise_id']}\n" .
               "- 目标次数: {$params['target_reps']}次\n" .
               ($historyText ?: "- 无历史记录\n") .
               (isset($params['current_1rm']) ? "- 当前1RM: {$params['current_1rm']}kg\n" : "") .
               "\n请给出建议重量(kg)、最小值、最大值和推荐理由。";
    }

    /**
     * 构建RPE推荐提示词
     */
    private function buildRPEPrompt(array $params): string
    {
        return "请推荐RPE范围：\n\n" .
               "- 动作ID: {$params['exercise_id']}\n" .
               "- 训练阶段: {$params['training_phase']}\n" .
               "- 当前周次: {$params['current_week']}/{$params['total_weeks']}\n" .
               "\n请给出RPE范围(最小值、最大值、目标值)和推荐理由。";
    }

    /**
     * 解析训练计划（简化版，实际需要更复杂的解析）
     */
    private function parseTrainingPlan(string $response): array
    {
        // TODO: 实现更完善的解析逻辑
        return [
            'plan_name' => 'AI生成计划',
            'duration_weeks' => 4,
            'sessions' => [],
            'raw_response' => $response,
        ];
    }

    /**
     * 解析重量推荐
     */
    private function parseWeightRecommendation(string $response): array
    {
        // TODO: 实现解析逻辑
        return [
            'suggested_weight_kg' => 0,
            'confidence' => 0.8,
            'reasoning' => $response,
        ];
    }

    /**
     * 解析RPE推荐
     */
    private function parseRPERecommendation(string $response): array
    {
        // TODO: 实现解析逻辑
        return [
            'min_rpe' => 7,
            'max_rpe' => 9,
            'target_rpe' => 8,
            'reasoning' => $response,
        ];
    }
}
