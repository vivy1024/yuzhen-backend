<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;
use Exception;

/**
 * 健康检查控制器
 * 
 * 提供系统和各组件的健康状态检查
 */
class HealthCheckController extends Controller
{
    /**
     * 基础健康检查
     * 
     * GET /api/health
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'code' => 200,
            'msg' => 'OK',
            'data' => [
                'status' => 'healthy',
                'version' => '2.0.0',
                'timestamp' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * CORS配置检查
     * 
     * GET /api/health/cors
     * 
     * @return JsonResponse
     */
    public function cors(): JsonResponse
    {
        return response()->json([
            'code' => 200,
            'msg' => 'OK',
            'data' => [
                'cors_allowed_origins' => config('cors.allowed_origins'),
                'cors_allowed_origins_env' => env('CORS_ALLOWED_ORIGINS'),
                'cors_paths' => config('cors.paths'),
                'cors_supports_credentials' => config('cors.supports_credentials'),
            ]
        ]);
    }

    /**
     * 组件健康状态检查
     * 
     * GET /api/health/components
     * 
     * 检查各个数据库和服务的连接状态
     * 
     * @return JsonResponse
     */
    public function components(): JsonResponse
    {
        $components = [
            'mysql' => $this->checkMySQL(),
            'redis' => $this->checkRedis(),
            'neo4j' => $this->checkNeo4j(),
            'qdrant' => $this->checkQdrant(),
            'daml_rag' => $this->checkDamlRag(),
        ];

        // 计算整体健康状态
        $healthyCount = count(array_filter($components, fn($status) => $status['status'] === 'healthy'));
        $totalCount = count($components);
        
        $overallStatus = match(true) {
            $healthyCount === $totalCount => 'healthy',
            $healthyCount > 0 => 'degraded',
            default => 'unhealthy'
        };

        return response()->json([
            'code' => 200,
            'msg' => 'OK',
            'data' => [
                'status' => $overallStatus,
                'timestamp' => now()->toISOString(),
                'components' => $components,
                'summary' => [
                    'total' => $totalCount,
                    'healthy' => $healthyCount,
                    'unhealthy' => $totalCount - $healthyCount,
                ]
            ]
        ]);
    }

    /**
     * 检查MySQL连接状态
     */
    private function checkMySQL(): array
    {
        try {
            DB::connection()->getPdo();
            $version = DB::select('SELECT VERSION() as version')[0]->version ?? 'unknown';
            
            return [
                'status' => 'healthy',
                'message' => 'MySQL连接正常',
                'version' => $version,
                'response_time_ms' => $this->measureResponseTime(fn() => DB::connection()->getPdo())
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'MySQL连接失败',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 检查Redis连接状态
     */
    private function checkRedis(): array
    {
        try {
            $startTime = microtime(true);
            Redis::ping();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => 'healthy',
                'message' => 'Redis连接正常',
                'response_time_ms' => $responseTime
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Redis连接失败',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 检查Neo4j连接状态
     */
    private function checkNeo4j(): array
    {
        try {
            $neo4jUrl = config('database.connections.neo4j.url', env('NEO4J_URL'));
            $neo4jUser = config('database.connections.neo4j.username', env('NEO4J_USERNAME'));
            $neo4jPassword = config('database.connections.neo4j.password', env('NEO4J_PASSWORD'));

            if (!$neo4jUrl) {
                return [
                    'status' => 'unknown',
                    'message' => 'Neo4j未配置'
                ];
            }

            $startTime = microtime(true);
            $response = Http::timeout(5)
                ->withBasicAuth($neo4jUser, $neo4jPassword)
                ->get($neo4jUrl);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                return [
                    'status' => 'healthy',
                    'message' => 'Neo4j连接正常',
                    'response_time_ms' => $responseTime
                ];
            }

            return [
                'status' => 'unhealthy',
                'message' => 'Neo4j连接失败',
                'http_status' => $response->status()
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Neo4j连接失败',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 检查Qdrant连接状态
     */
    private function checkQdrant(): array
    {
        try {
            $qdrantUrl = env('QDRANT_URL');
            
            if (!$qdrantUrl) {
                return [
                    'status' => 'unknown',
                    'message' => 'Qdrant未配置'
                ];
            }

            $startTime = microtime(true);
            $response = Http::timeout(5)->get("{$qdrantUrl}/");
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                return [
                    'status' => 'healthy',
                    'message' => 'Qdrant连接正常',
                    'response_time_ms' => $responseTime
                ];
            }

            return [
                'status' => 'unhealthy',
                'message' => 'Qdrant连接失败',
                'http_status' => $response->status()
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Qdrant连接失败',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 检查DAML-RAG服务状态
     */
    private function checkDamlRag(): array
    {
        try {
            $damlRagUrl = env('DAML_RAG_URL');
            
            if (!$damlRagUrl) {
                return [
                    'status' => 'unknown',
                    'message' => 'DAML-RAG未配置'
                ];
            }

            $startTime = microtime(true);
            $response = Http::timeout(5)->get("{$damlRagUrl}/api/health");
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => 'healthy',
                    'message' => 'DAML-RAG服务正常',
                    'response_time_ms' => $responseTime,
                    'service_status' => $data['status'] ?? 'unknown'
                ];
            }

            return [
                'status' => 'unhealthy',
                'message' => 'DAML-RAG服务连接失败',
                'http_status' => $response->status()
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'DAML-RAG服务连接失败',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 测量响应时间
     */
    private function measureResponseTime(callable $callback): float
    {
        $startTime = microtime(true);
        $callback();
        return round((microtime(true) - $startTime) * 1000, 2);
    }
}
