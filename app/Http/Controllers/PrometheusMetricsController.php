<?php

namespace App\Http\Controllers;

use App\Infrastructure\Http\Controllers\BaseController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Prometheus Metrics 端点
 *
 * 暴露 Laravel 应用指标供 Prometheus 采集
 *
 * GET /metrics
 *
 * 注意：此端点不需要认证，但应通过网络策略限制仅 Prometheus 可访问
 *
 * @version v1.0.0
 * @date 2026-02-22
 */
class PrometheusMetricsController extends BaseController
{
    /**
     * 输出 Prometheus 格式的指标
     */
    public function metrics(): Response
    {
        $lines = [];

        // 1. HTTP 请求计数（从 Redis 缓存读取，由中间件写入）
        $httpTotal = (int) Cache::get('metrics:http_requests_total', 0);
        $httpErrors = (int) Cache::get('metrics:http_errors_total', 0);
        $lines[] = '# HELP http_requests_total Total HTTP requests';
        $lines[] = '# TYPE http_requests_total counter';
        $lines[] = "http_requests_total {$httpTotal}";
        $lines[] = '# HELP http_errors_total Total HTTP 5xx errors';
        $lines[] = '# TYPE http_errors_total counter';
        $lines[] = "http_errors_total {$httpErrors}";

        // 2. 数据库连接池
        try {
            $dbConnections = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            $threadCount = $dbConnections[0]->Value ?? 0;
            $lines[] = '# HELP mysql_threads_connected Current MySQL connections';
            $lines[] = '# TYPE mysql_threads_connected gauge';
            $lines[] = "mysql_threads_connected {$threadCount}";
        } catch (\Exception $e) {
            // DB 不可用时跳过
        }

        // 3. 应用级指标
        $todayQueries = (int) Cache::get('metrics:today_queries', 0);
        $lines[] = '# HELP app_queries_today Total AI queries today';
        $lines[] = '# TYPE app_queries_today gauge';
        $lines[] = "app_queries_today {$todayQueries}";

        $activeUsers = (int) Cache::get('metrics:active_users_5m', 0);
        $lines[] = '# HELP app_active_users_5m Active users in last 5 minutes';
        $lines[] = '# TYPE app_active_users_5m gauge';
        $lines[] = "app_active_users_5m {$activeUsers}";

        // 4. PHP 进程信息
        $memUsage = memory_get_usage(true);
        $lines[] = '# HELP php_memory_usage_bytes PHP memory usage';
        $lines[] = '# TYPE php_memory_usage_bytes gauge';
        $lines[] = "php_memory_usage_bytes {$memUsage}";

        $content = implode("\n", $lines) . "\n";

        return response($content, 200)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}
