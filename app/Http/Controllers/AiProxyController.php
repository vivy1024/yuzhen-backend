<?php

namespace App\Http\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use App\Http\Requests\AiChatRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AI代理控制器
 * 
 * 将前端的AI请求代理到YuzhenFork Agent服务
 * 
 * @version 2.0.0
 * @created 2026-01-13
 * @updated 2026-05-11 - 代理目标从 DAML-RAG 切换到 YuzhenFork Studio API
 */
class AiProxyController extends BaseController
{
    /**
     * YuzhenFork Agent 服务地址
     */
    private string $agentUrl;

    /**
     * DAML-RAG 服务地址（健康检查等辅助接口仍用）
     */
    private string $damlRagUrl;
    
    public function __construct()
    {
        $this->agentUrl = config('services.yuzhenfork.url', 'http://host.docker.internal:4567');
        $this->damlRagUrl = config('services.daml_rag.url', 'http://fitness_daml_rag:8001');
    }
    
    /**
     * 流式聊天接口
     * 
     * 代理到 YuzhenFork Studio API 的 headless-chat 端点
     * 
     * @param Request $request
     * @return StreamedResponse
     */
    public function streamChat(AiChatRequest $request): StreamedResponse
    {
        $data = $request->validated();
        
        Log::info('[AiProxy] 流式聊天请求 → YuzhenFork', [
            'user_id' => $data['user_id'] ?? null,
            'query' => substr($data['query'] ?? '', 0, 100),
            'session_id' => $data['session_id'] ?? $data['thread_id'] ?? null,
        ]);
        
        return new StreamedResponse(function () use ($data) {
            $url = "{$this->agentUrl}/api/sessions/headless-chat";
            
            // 映射前端字段到 yuzhenfork headless-chat 格式
            $body = [
                'prompt' => $data['query'] ?? $data['message'] ?? '',
                'sessionId' => $data['session_id'] ?? $data['thread_id'] ?? null,
                'outputFormat' => 'stream-json',
            ];
            
            try {
                $ch = curl_init();
                
                $headers = [
                    'Content-Type: application/json',
                    'Accept: application/x-ndjson',
                ];
                
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($body),
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_WRITEFUNCTION => function ($ch, $chunk) {
                        // 将 NDJSON 转换为 SSE 格式透传给前端
                        $lines = explode("\n", $chunk);
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (empty($line)) continue;
                            
                            $event = json_decode($line, true);
                            if (!$event) {
                                // 原样透传非 JSON 行
                                echo $line . "\n";
                                continue;
                            }
                            
                            // 转换 NDJSON 事件为 SSE 格式
                            $type = $event['type'] ?? 'message';
                            echo "event: {$type}\n";
                            echo "data: " . json_encode($event) . "\n\n";
                        }
                        
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                        return strlen($chunk);
                    },
                    CURLOPT_TIMEOUT => 600,
                    CURLOPT_CONNECTTIMEOUT => 30,
                    CURLOPT_LOW_SPEED_LIMIT => 1,
                    CURLOPT_LOW_SPEED_TIME => 300,
                ]);
                
                $result = curl_exec($ch);
                
                if (curl_errno($ch)) {
                    $error = curl_error($ch);
                    Log::error('[AiProxy] YuzhenFork cURL错误', ['error' => $error]);
                    echo "event: error\n";
                    echo "data: " . json_encode([
                        'type' => 'error',
                        'error' => "连接AI服务失败: {$error}"
                    ]) . "\n\n";
                }
                
                curl_close($ch);
                
            } catch (\Exception $e) {
                Log::error('[AiProxy] YuzhenFork流式请求异常', [
                    'error' => $e->getMessage(),
                ]);
                
                echo "event: error\n";
                echo "data: " . json_encode([
                    'type' => 'error',
                    'error' => "AI服务异常: {$e->getMessage()}"
                ]) . "\n\n";
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    
    /**
     * 非流式聊天接口
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function chat(AiChatRequest $request)
    {
        $data = $request->validated();
        
        Log::info('[AiProxy] 非流式聊天请求', [
            'user_id' => $data['user_id'] ?? null,
            'query' => substr($data['query'] ?? '', 0, 100)
        ]);
        
        try {
            // 构建带认证头的HTTP请求
            $httpRequest = Http::timeout(120);
            
            if ($request->hasHeader('Authorization')) {
                $httpRequest = $httpRequest->withHeaders([
                    'Authorization' => $request->header('Authorization'),
                ]);
            }
            if ($request->hasHeader('X-Internal-Token')) {
                $httpRequest = $httpRequest->withHeaders([
                    'X-Internal-Token' => $request->header('X-Internal-Token'),
                ]);
            }
            
            $response = $httpRequest->post("{$this->damlRagUrl}/api/v1/chat", $data);
            
            if ($response->successful()) {
                return $this->success($response->json(), 'AI对话成功');
            }
            
            Log::error('[AiProxy] DAML-RAG响应错误', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return $this->fail('AI服务响应错误', $response->status());
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'AI非流式对话');
        }
    }
    
    /**
     * 健康检查 — 检查 YuzhenFork Agent 状态
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function health()
    {
        try {
            // 优先检查 YuzhenFork
            $agentResponse = Http::timeout(5)
                ->get("{$this->agentUrl}/api/health");
            
            $result = [
                'yuzhenfork' => $agentResponse->successful() ? $agentResponse->json() : ['status' => 'unavailable'],
            ];

            // 也检查 DAML-RAG（MCP 后端）
            try {
                $ragResponse = Http::timeout(5)
                    ->get("{$this->damlRagUrl}/api/health");
                $result['daml_rag'] = $ragResponse->successful() ? $ragResponse->json() : ['status' => 'unavailable'];
            } catch (\Exception $e) {
                $result['daml_rag'] = ['status' => 'unavailable', 'error' => $e->getMessage()];
            }

            return $this->success($result, 'AI服务状态');
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'AI健康检查');
        }
    }

    /**
     * 用户预热接口代理
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function warmup(Request $request)
    {
        $data = $request->all();

        try {
            $httpRequest = Http::timeout(10);

            if ($request->hasHeader('Authorization')) {
                $httpRequest = $httpRequest->withHeaders([
                    'Authorization' => $request->header('Authorization'),
                ]);
            }
            if ($request->hasHeader('X-Internal-Token')) {
                $httpRequest = $httpRequest->withHeaders([
                    'X-Internal-Token' => $request->header('X-Internal-Token'),
                ]);
            }

            $response = $httpRequest->post("{$this->damlRagUrl}/api/v1/user/warmup", $data);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return $this->fail('预热服务响应错误', $response->status());

        } catch (\Exception $e) {
            return $this->handleException($e, '用户预热');
        }
    }

    /**
     * 用户预热状态查询代理
     *
     * @param string $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function warmupStatus(Request $request, string $userId)
    {
        // 验证只能查询自己的预热状态
        if ((string) $request->user()->id !== $userId) {
            return $this->fail('无权查看其他用户的预热状态', 403);
        }

        try {
            $httpRequest = Http::timeout(10);

            if ($request->hasHeader('Authorization')) {
                $httpRequest = $httpRequest->withHeaders([
                    'Authorization' => $request->header('Authorization'),
                ]);
            }
            if ($request->hasHeader('X-Internal-Token')) {
                $httpRequest = $httpRequest->withHeaders([
                    'X-Internal-Token' => $request->header('X-Internal-Token'),
                ]);
            }

            $response = $httpRequest->get("{$this->damlRagUrl}/api/v1/user/warmup/status/{$userId}");

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return $this->fail('预热状态查询失败', $response->status());

        } catch (\Exception $e) {
            return $this->handleException($e, '预热状态查询');
        }
    }

}
