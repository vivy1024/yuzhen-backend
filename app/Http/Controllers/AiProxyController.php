<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AI代理控制器
 * 
 * 将前端的AI请求代理到DAML-RAG服务
 * 
 * @version 1.0.0
 * @created 2026-01-13
 */
class AiProxyController extends Controller
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
        
        return new StreamedResponse(function () use ($data) {
            $url = "{$this->damlRagUrl}/api/v1/chat/stream";
            
            try {
                // 使用cURL进行流式请求
                $ch = curl_init();
                
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Accept: text/event-stream',
                    ],
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
            $response = Http::timeout(120)
                ->post("{$this->damlRagUrl}/api/v1/chat", $data);
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            Log::error('[AiProxy] DAML-RAG响应错误', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return response()->json([
                'code' => $response->status(),
                'msg' => 'AI服务响应错误',
                'data' => null
            ], $response->status());
            
        } catch (\Exception $e) {
            Log::error('[AiProxy] 非流式请求异常', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'code' => 500,
                'msg' => "AI服务异常: {$e->getMessage()}",
                'data' => null
            ], 500);
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
                return response()->json([
                    'code' => 200,
                    'msg' => 'OK',
                    'data' => $response->json()
                ]);
            }
            
            return response()->json([
                'code' => $response->status(),
                'msg' => 'AI服务不可用',
                'data' => null
            ], $response->status());
            
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'msg' => "AI服务连接失败: {$e->getMessage()}",
                'data' => null
            ], 500);
        }
    }
}
