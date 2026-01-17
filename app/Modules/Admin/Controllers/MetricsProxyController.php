<?php

namespace App\Modules\Admin\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Prometheus指标代理控制器
 * 
 * 代理查询Prometheus，避免直接暴露Prometheus端口
 * 支持PromQL查询和范围查询
 * 
 * @version v1.1.0
 * @date 2026-01-17 (修复API响应规范合规性)
 */
class MetricsProxyController extends BaseController
{
    /**
     * Prometheus服务地址
     */
    private string $prometheusUrl;
    
    /**
     * DAML-RAG服务地址
     */
    private string $damlRagUrl;
    
    public function __construct()
    {
        $this->prometheusUrl = env('PROMETHEUS_URL', 'http://prometheus:9090');
        $this->damlRagUrl = env('DAML_RAG_URL', 'http://fitness_daml_rag:8001');
    }
    
    /**
     * 即时查询 - 查询当前时间点的指标值
     * 
     * GET /api/admin/metrics/query
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function query(Request $request)
    {
        try {
            $query = $request->input('query');
            $time = $request->input('time');
            
            if (!$query) {
                return $this->fail('缺少query参数', 400);
            }
            
            $params = ['query' => $query];
            if ($time) {
                $params['time'] = $time;
            }
            
            $response = Http::timeout(10)->get("{$this->prometheusUrl}/api/v1/query", $params);
            
            if ($response->successful()) {
                return $this->success($response->json(), 'success');
            }
            
            return $this->fail('Prometheus查询失败', $response->status(), $response->json());
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'Prometheus查询');
        }
    }
    
    /**
     * 范围查询 - 查询时间范围内的指标值
     * 
     * GET /api/admin/metrics/query_range
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function queryRange(Request $request)
    {
        try {
            $query = $request->input('query');
            $start = $request->input('start');
            $end = $request->input('end');
            $step = $request->input('step', '15s');
            
            if (!$query || !$start || !$end) {
                return $this->fail('缺少必要参数(query, start, end)', 400);
            }
            
            $response = Http::timeout(30)->get("{$this->prometheusUrl}/api/v1/query_range", [
                'query' => $query,
                'start' => $start,
                'end' => $end,
                'step' => $step
            ]);
            
            if ($response->successful()) {
                return $this->success($response->json(), 'success');
            }
            
            return $this->fail('Prometheus范围查询失败', $response->status(), $response->json());
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'Prometheus范围查询');
        }
    }
    
    /**
     * 批量查询 - 一次查询多个指标
     * 
     * POST /api/admin/metrics/batch
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchQuery(Request $request)
    {
        try {
            $queries = $request->input('queries', []);
            $start = $request->input('start');
            $end = $request->input('end');
            $step = $request->input('step', '15s');
            
            if (empty($queries)) {
                return $this->fail('缺少queries参数', 400);
            }
            
            $results = [];
            
            foreach ($queries as $name => $query) {
                try {
                    if ($start && $end) {
                        // 范围查询
                        $response = Http::timeout(15)->get("{$this->prometheusUrl}/api/v1/query_range", [
                            'query' => $query,
                            'start' => $start,
                            'end' => $end,
                            'step' => $step
                        ]);
                    } else {
                        // 即时查询
                        $response = Http::timeout(10)->get("{$this->prometheusUrl}/api/v1/query", [
                            'query' => $query
                        ]);
                    }
                    
                    if ($response->successful()) {
                        $results[$name] = [
                            'status' => 'success',
                            'data' => $response->json()
                        ];
                    } else {
                        $results[$name] = [
                            'status' => 'error',
                            'error' => 'Query failed'
                        ];
                    }
                } catch (\Exception $e) {
                    $results[$name] = [
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return $this->success($results, 'success');
            
        } catch (\Exception $e) {
            return $this->handleException($e, '批量查询');
        }
    }
    
    /**
     * 获取DAML-RAG健康状态
     * 
     * GET /api/admin/metrics/daml-rag/health
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function damlRagHealth()
    {
        try {
            $response = Http::timeout(10)->get("{$this->damlRagUrl}/api/health");
            
            if ($response->successful()) {
                return $this->success($response->json(), 'success');
            }
            
            return $this->fail('DAML-RAG健康检查失败', $response->status());
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'DAML-RAG健康检查');
        }
    }
    
    /**
     * 获取DAML-RAG系统指标
     * 
     * GET /api/admin/metrics/daml-rag/metrics
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function damlRagMetrics()
    {
        try {
            $response = Http::timeout(10)->get("{$this->damlRagUrl}/api/health/metrics");
            
            if ($response->successful()) {
                return $this->success($response->json(), 'success');
            }
            
            return $this->fail('DAML-RAG指标获取失败', $response->status());
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'DAML-RAG指标获取');
        }
    }
    
    /**
     * 获取流式监控统计
     * 
     * GET /api/admin/metrics/daml-rag/streaming
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function damlRagStreaming(Request $request)
    {
        try {
            $timeWindow = $request->input('time_window', 3600);
            
            $response = Http::timeout(10)->get("{$this->damlRagUrl}/api/health/metrics/streaming", [
                'time_window' => $timeWindow
            ]);
            
            if ($response->successful()) {
                return $this->success($response->json(), 'success');
            }
            
            return $this->fail('流式监控获取失败', $response->status());
            
        } catch (\Exception $e) {
            return $this->handleException($e, '流式监控获取');
        }
    }
    
    /**
     * 获取最近的流式会话记录
     * 
     * GET /api/admin/metrics/daml-rag/streaming/recent
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function damlRagStreamingRecent(Request $request)
    {
        try {
            $limit = $request->input('limit', 100);
            
            $response = Http::timeout(10)->get("{$this->damlRagUrl}/api/health/metrics/streaming/recent", [
                'limit' => $limit
            ]);
            
            if ($response->successful()) {
                return $this->success($response->json(), 'success');
            }
            
            return $this->fail('流式会话记录获取失败', $response->status());
            
        } catch (\Exception $e) {
            return $this->handleException($e, '流式会话记录获取');
        }
    }
    
    /**
     * 获取DAML-RAG日志
     * 
     * GET /api/admin/metrics/daml-rag/logs
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function damlRagLogs(Request $request)
    {
        $lines = $request->input('lines', 200);
        $level = $request->input('level', 'all');
        
        try {
            // 读取DAML-RAG日志文件
            // 日志文件在Docker容器内，通过挂载的volume访问
            $logPath = base_path('../data/logs/daml-rag.log');
            $errorLogPath = base_path('../data/logs/daml-rag-error.log');
            
            $logs = [];
            
            // 读取主日志
            if (file_exists($logPath)) {
                $content = $this->tailFile($logPath, $lines);
                $logs = array_merge($logs, $this->parseLogLines($content));
            }
            
            // 如果请求错误日志，也读取错误日志文件
            if ($level === 'ERROR' || $level === 'all') {
                if (file_exists($errorLogPath)) {
                    $errorContent = $this->tailFile($errorLogPath, $lines);
                    $logs = array_merge($logs, $this->parseLogLines($errorContent, 'ERROR'));
                }
            }
            
            // 按时间排序（最新的在前）
            usort($logs, function($a, $b) {
                return strtotime($b['timestamp'] ?? '1970-01-01') - strtotime($a['timestamp'] ?? '1970-01-01');
            });
            
            // 过滤级别
            if ($level !== 'all') {
                $logs = array_filter($logs, function($log) use ($level) {
                    return ($log['level'] ?? 'INFO') === $level;
                });
            }
            
            // 限制返回数量
            $logs = array_slice(array_values($logs), 0, $lines);
            
            // 计算统计
            $stats = [
                'total' => count($logs),
                'error' => count(array_filter($logs, fn($l) => ($l['level'] ?? '') === 'ERROR')),
                'warn' => count(array_filter($logs, fn($l) => in_array($l['level'] ?? '', ['WARN', 'WARNING']))),
                'info' => count(array_filter($logs, fn($l) => ($l['level'] ?? '') === 'INFO')),
                'debug' => count(array_filter($logs, fn($l) => ($l['level'] ?? '') === 'DEBUG'))
            ];
            
            return response()->json([
                'code' => 200,
                'msg' => 'success',
                'data' => [
                    'logs' => $logs,
                    'stats' => $stats
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('读取DAML-RAG日志失败', ['error' => $e->getMessage()]);
            
            return response()->json([
                'code' => 500,
                'msg' => '日志读取失败: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    
    /**
     * 读取文件最后N行
     */
    private function tailFile(string $path, int $lines): string
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);
        
        $content = '';
        while (!$file->eof()) {
            $content .= $file->fgets();
        }
        
        return $content;
    }
    
    /**
     * 解析日志行
     */
    private function parseLogLines(string $content, string $defaultLevel = 'INFO'): array
    {
        $logs = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // 尝试解析JSON格式日志
            if (str_starts_with($line, '{')) {
                try {
                    $json = json_decode($line, true);
                    if ($json) {
                        $logs[] = [
                            'id' => uniqid('log_'),
                            'timestamp' => $json['timestamp'] ?? date('c'),
                            'level' => strtoupper($json['level'] ?? $defaultLevel),
                            'message' => $json['message'] ?? $line,
                            'component' => $json['logger_name'] ?? $json['component'] ?? 'daml_rag',
                            'trace_id' => $json['trace_id'] ?? null,
                            'duration_ms' => $json['duration_ms'] ?? null
                        ];
                        continue;
                    }
                } catch (\Exception $e) {
                    // 不是JSON，继续尝试其他格式
                }
            }
            
            // 尝试解析标准日志格式: 2026-01-06 14:00:00,123 - logger - LEVEL - message
            if (preg_match('/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}),?\d*\s*-\s*([^\s]+)\s*-\s*(INFO|DEBUG|WARNING|WARN|ERROR|CRITICAL)\s*-\s*(.+)$/i', $line, $matches)) {
                $logs[] = [
                    'id' => uniqid('log_'),
                    'timestamp' => $matches[1],
                    'level' => strtoupper($matches[3]),
                    'message' => $matches[4],
                    'component' => $matches[2],
                    'trace_id' => null,
                    'duration_ms' => null
                ];
                continue;
            }
            
            // 尝试解析uvicorn格式: INFO:     IP - "METHOD /path HTTP/1.1" STATUS
            if (preg_match('/^(INFO|DEBUG|WARNING|WARN|ERROR):\s+(.+)$/i', $line, $matches)) {
                $logs[] = [
                    'id' => uniqid('log_'),
                    'timestamp' => date('c'),
                    'level' => strtoupper($matches[1]),
                    'message' => $matches[2],
                    'component' => 'uvicorn',
                    'trace_id' => null,
                    'duration_ms' => null
                ];
                continue;
            }
            
            // 无法解析的行，作为INFO处理
            if (strlen($line) > 10) {
                $logs[] = [
                    'id' => uniqid('log_'),
                    'timestamp' => date('c'),
                    'level' => $defaultLevel,
                    'message' => $line,
                    'component' => 'unknown',
                    'trace_id' => null,
                    'duration_ms' => null
                ];
            }
        }
        
        return $logs;
    }
    
    /**
     * 从Loki查询日志
     * 
     * GET /api/admin/metrics/loki/query
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function lokiQuery(Request $request)
    {
        $query = $request->input('query', '{job="daml-rag-files"}');
        $limit = $request->input('limit', 100);
        $start = $request->input('start');
        $end = $request->input('end');
        
        try {
            $lokiUrl = env('LOKI_URL', 'http://loki:3100');
            
            $params = [
                'query' => $query,
                'limit' => $limit
            ];
            
            if ($start) $params['start'] = $start;
            if ($end) $params['end'] = $end;
            
            $response = Http::timeout(15)->get("{$lokiUrl}/loki/api/v1/query_range", $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                // 解析Loki响应格式
                $logs = [];
                if (isset($data['data']['result'])) {
                    foreach ($data['data']['result'] as $stream) {
                        $labels = $stream['stream'] ?? [];
                        foreach ($stream['values'] ?? [] as $value) {
                            $timestamp = $value[0] ?? '';
                            $message = $value[1] ?? '';
                            
                            // 尝试解析JSON格式的日志
                            $parsed = json_decode($message, true);
                            
                            $logs[] = [
                                'id' => uniqid('loki_'),
                                'timestamp' => date('c', intval($timestamp) / 1000000000),
                                'level' => $parsed['level'] ?? $labels['level'] ?? 'INFO',
                                'message' => $parsed['message'] ?? $message,
                                'component' => $parsed['logger_name'] ?? $labels['logger'] ?? 'daml_rag',
                                'trace_id' => $parsed['trace_id'] ?? null,
                                'labels' => $labels
                            ];
                        }
                    }
                }
                
                // 按时间排序（最新的在前）
                usort($logs, function($a, $b) {
                    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
                });
                
                // 计算统计
                $stats = [
                    'total' => count($logs),
                    'error' => count(array_filter($logs, fn($l) => strtoupper($l['level']) === 'ERROR')),
                    'warn' => count(array_filter($logs, fn($l) => in_array(strtoupper($l['level']), ['WARN', 'WARNING']))),
                    'info' => count(array_filter($logs, fn($l) => strtoupper($l['level']) === 'INFO')),
                    'debug' => count(array_filter($logs, fn($l) => strtoupper($l['level']) === 'DEBUG'))
                ];
                
                return response()->json([
                    'code' => 200,
                    'msg' => 'success',
                    'data' => [
                        'logs' => $logs,
                        'stats' => $stats,
                        'source' => 'loki'
                    ]
                ]);
            }
            
            return response()->json([
                'code' => $response->status(),
                'msg' => 'Loki查询失败',
                'data' => null
            ], $response->status());
            
        } catch (\Exception $e) {
            Log::warning('Loki查询失败，回退到文件读取', ['error' => $e->getMessage()]);
            
            // 回退到文件读取
            return $this->damlRagLogs($request);
        }
    }
    
    /**
     * 获取Loki日志标签
     * 
     * GET /api/admin/metrics/loki/labels
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function lokiLabels()
    {
        try {
            $lokiUrl = env('LOKI_URL', 'http://loki:3100');
            $response = Http::timeout(10)->get("{$lokiUrl}/loki/api/v1/labels");
            
            if ($response->successful()) {
                return response()->json([
                    'code' => 200,
                    'msg' => 'success',
                    'data' => $response->json()
                ]);
            }
            
            return response()->json([
                'code' => $response->status(),
                'msg' => 'Loki标签获取失败',
                'data' => null
            ], $response->status());
            
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'msg' => 'Loki服务不可用: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    
    /**
     * 获取Prometheus原始指标（文本格式）
     * 
     * GET /api/admin/metrics/prometheus/raw
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function prometheusRaw()
    {
        try {
            $response = Http::timeout(10)->get("{$this->damlRagUrl}/api/health/metrics/prometheus");
            
            if ($response->successful()) {
                // 解析Prometheus文本格式
                $metrics = $this->parsePrometheusText($response->body());
                
                return response()->json([
                    'code' => 200,
                    'msg' => 'success',
                    'data' => $metrics
                ]);
            }
            
            return response()->json([
                'code' => $response->status(),
                'msg' => 'Prometheus指标获取失败',
                'data' => null
            ], $response->status());
            
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'msg' => '服务不可用: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    
    /**
     * 解析Prometheus文本格式
     * 
     * @param string $text
     * @return array
     */
    private function parsePrometheusText(string $text): array
    {
        $metrics = [];
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // 跳过注释和空行
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            
            // 解析指标行: metric_name{labels} value
            if (preg_match('/^([a-zA-Z_:][a-zA-Z0-9_:]*)\{?([^}]*)\}?\s+(.+)$/', $line, $matches)) {
                $name = $matches[1];
                $labelsStr = $matches[2];
                $value = $matches[3];
                
                // 解析标签
                $labels = [];
                if (!empty($labelsStr)) {
                    preg_match_all('/([a-zA-Z_][a-zA-Z0-9_]*)="([^"]*)"/', $labelsStr, $labelMatches, PREG_SET_ORDER);
                    foreach ($labelMatches as $labelMatch) {
                        $labels[$labelMatch[1]] = $labelMatch[2];
                    }
                }
                
                $metrics[] = [
                    'name' => $name,
                    'labels' => $labels,
                    'value' => is_numeric($value) ? floatval($value) : $value
                ];
            }
        }
        
        return $metrics;
    }
}
