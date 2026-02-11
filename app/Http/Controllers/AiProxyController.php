<?php

namespace App\Http\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AI代理控制器
 * 
 * 将前端的AI请求代理到DAML-RAG服务
 * 
 * @version 1.1.0
 * @created 2026-01-13
 * @updated 2026-01-17 - 修复API响应规范合规性
 */
class AiProxyController extends BaseController
{
    /**
     * DAML-RAG服务地址
     */
    private string $damlRagUrl;
    
    public function __construct()
    {
        $this->damlRagUrl = env('DAML_RAG_URL', 'http://fitness_daml_rag:8001');
    }
    
    /**
     * 流式聊天接口
     * 
     * @param Request $request
     * @return StreamedResponse
     */
    public function streamChat(Request $request): StreamedResponse
    {
        $data = $request->all();
        
        Log::info('[AiProxy] 流式聊天请求', [
            'user_id' => $data['user_id'] ?? null,
            'query' => substr($data['query'] ?? '', 0, 100),
            'strategy' => $data['strategy'] ?? 'dag'
        ]);
        
        // 从中间件获取认证头（InternalJwtForward已设置）
        $authHeaders = [];
        if ($request->hasHeader('Authorization')) {
            $authHeaders[] = 'Authorization: ' . $request->header('Authorization');
        }
        if ($request->hasHeader('X-Internal-Token')) {
            $authHeaders[] = 'X-Internal-Token: ' . $request->header('X-Internal-Token');
        }
        
        return new StreamedResponse(function () use ($data, $authHeaders) {
            $url = "{$this->damlRagUrl}/api/v1/chat/stream";
            
            try {
                // 使用cURL进行流式请求
                $ch = curl_init();
                
                $headers = array_merge([
                    'Content-Type: application/json',
                    'Accept: text/event-stream',
                ], $authHeaders);
                
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_WRITEFUNCTION => function ($ch, $chunk) {
                        echo $chunk;
                        ob_flush();
                        flush();
                        return strlen($chunk);
                    },
                    CURLOPT_TIMEOUT => 300, // 5分钟超时
                    CURLOPT_CONNECTTIMEOUT => 30,
                ]);
                
                $result = curl_exec($ch);
                
                if (curl_errno($ch)) {
                    $error = curl_error($ch);
                    Log::error('[AiProxy] cURL错误', ['error' => $error]);
                    echo "data: " . json_encode([
                        'type' => 'error',
                        'error' => "连接AI服务失败: {$error}"
                    ]) . "\n\n";
                }
                
                curl_close($ch);
                
            } catch (\Exception $e) {
                Log::error('[AiProxy] 流式请求异常', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                echo "data: " . json_encode([
                    'type' => 'error',
                    'error' => "AI服务异常: {$e->getMessage()}"
                ]) . "\n\n";
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // 禁用nginx缓冲
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ]);
    }

    
    /**
     * 非流式聊天接口
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function chat(Request $request)
    {
        $data = $request->all();
        
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
     * 健康检查
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function health()
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->damlRagUrl}/api/health");
            
            if ($response->successful()) {
                return $this->success($response->json(), 'AI服务正常');
            }
            
            return $this->fail('AI服务不可用', $response->status());
            
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
            $response = Http::timeout(10)
                ->post("{$this->damlRagUrl}/api/v1/user/warmup", $data);

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
    public function warmupStatus(string $userId)
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->damlRagUrl}/api/v1/user/warmup/status/{$userId}");

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return $this->fail('预热状态查询失败', $response->status());

        } catch (\Exception $e) {
            return $this->handleException($e, '预热状态查询');
        }
    }

}
