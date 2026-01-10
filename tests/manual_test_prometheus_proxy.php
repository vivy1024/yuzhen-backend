<?php

/**
 * 手动测试Prometheus代理功能
 * 
 * 这个脚本直接测试PHP后端能否调用DAML-RAG的Prometheus端点
 * 不涉及认证，只测试核心功能
 * 
 * Feature: monitoring-simplification
 * Task: 11.1 测试/admin/metrics/prometheus/raw端点
 * Validates: Requirements 4.4, 5.7
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// 加载环境变量
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Prometheus代理功能测试 ===\n\n";

// DAML-RAG服务地址
$damlRagUrl = env('DAML_RAG_URL', 'http://fitness_daml_rag:8001');

echo "DAML-RAG URL: $damlRagUrl\n\n";

// 测试1: 直接调用DAML-RAG的Prometheus端点
echo "测试1: 直接调用DAML-RAG的/api/health/metrics/prometheus端点\n";
echo "------------------------------------------------------\n";

try {
    $response = Http::timeout(10)->get("{$damlRagUrl}/api/health/metrics/prometheus");
    
    if ($response->successful()) {
        $body = $response->body();
        $lines = explode("\n", $body);
        $metricCount = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && !str_starts_with($line, '#')) {
                $metricCount++;
            }
        }
        
        echo "✅ 成功调用DAML-RAG Prometheus端点\n";
        echo "   - HTTP状态码: " . $response->status() . "\n";
        echo "   - 响应大小: " . strlen($body) . " bytes\n";
        echo "   - 指标行数: $metricCount\n";
        echo "   - 前5行:\n";
        
        $displayLines = array_slice($lines, 0, 5);
        foreach ($displayLines as $line) {
            echo "     " . $line . "\n";
        }
    } else {
        echo "❌ 调用失败\n";
        echo "   - HTTP状态码: " . $response->status() . "\n";
        echo "   - 响应: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "❌ 异常: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试2: 解析Prometheus文本格式
echo "测试2: 解析Prometheus文本格式\n";
echo "------------------------------------------------------\n";

try {
    $response = Http::timeout(10)->get("{$damlRagUrl}/api/health/metrics/prometheus");
    
    if ($response->successful()) {
        $text = $response->body();
        $metrics = parsePrometheusText($text);
        
        echo "✅ 成功解析Prometheus格式\n";
        echo "   - 解析出的指标数量: " . count($metrics) . "\n";
        
        if (count($metrics) > 0) {
            echo "   - 第一个指标:\n";
            $first = $metrics[0];
            echo "     名称: " . $first['name'] . "\n";
            echo "     值: " . $first['value'] . "\n";
            echo "     标签: " . json_encode($first['labels']) . "\n";
        }
        
        // 统计指标类型
        $metricNames = array_unique(array_column($metrics, 'name'));
        echo "   - 唯一指标名称数量: " . count($metricNames) . "\n";
        echo "   - 前5个指标名称:\n";
        foreach (array_slice($metricNames, 0, 5) as $name) {
            echo "     - $name\n";
        }
    } else {
        echo "❌ 调用失败\n";
    }
} catch (Exception $e) {
    echo "❌ 异常: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试3: 测试其他DAML-RAG端点
echo "测试3: 测试其他DAML-RAG监控端点\n";
echo "------------------------------------------------------\n";

$endpoints = [
    '/api/health' => 'Health Check',
    '/api/health/metrics' => 'System Metrics',
    '/api/health/metrics/streaming' => 'Streaming Metrics'
];

foreach ($endpoints as $endpoint => $name) {
    try {
        $response = Http::timeout(10)->get("{$damlRagUrl}{$endpoint}");
        
        if ($response->successful()) {
            $data = $response->json();
            echo "✅ $name: 成功\n";
            echo "   - 端点: $endpoint\n";
            echo "   - 数据键: " . implode(', ', array_keys($data)) . "\n";
        } else {
            echo "❌ $name: 失败 (HTTP {$response->status()})\n";
        }
    } catch (Exception $e) {
        echo "❌ $name: 异常 - " . $e->getMessage() . "\n";
    }
}

echo "\n=== 测试完成 ===\n";

/**
 * 解析Prometheus文本格式
 */
function parsePrometheusText(string $text): array
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
