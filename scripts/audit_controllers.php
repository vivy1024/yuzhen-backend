<?php

/**
 * 控制器审查脚本
 * 
 * 检查所有控制器是否符合API响应规范：
 * 1. 是否继承自BaseController
 * 2. 是否使用success/fail方法
 * 3. 是否直接使用response()->json()
 * 4. 是否使用handleException方法
 */

$baseDir = __DIR__ . '/../app';
$results = [
    'inheritance_issues' => [],
    'direct_json_usage' => [],
    'missing_exception_handling' => [],
    'compliant_controllers' => [],
];

// 递归查找所有控制器文件
function findControllers($dir) {
    $controllers = [];
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            $controllers = array_merge($controllers, findControllers($path));
        } elseif (str_ends_with($item, 'Controller.php')) {
            $controllers[] = $path;
        }
    }
    
    return $controllers;
}

// 分析控制器文件
function analyzeController($filePath) {
    $content = file_get_contents($filePath);
    $relativePath = str_replace(__DIR__ . '/../app/', '', $filePath);
    
    $analysis = [
        'path' => $relativePath,
        'extends_base' => false,
        'uses_success_fail' => false,
        'uses_direct_json' => false,
        'uses_handle_exception' => false,
        'has_try_catch' => false,
    ];
    
    // 检查是否继承BaseController
    if (preg_match('/extends\s+BaseController/', $content)) {
        $analysis['extends_base'] = true;
    }
    
    // 检查是否使用success/fail方法
    if (preg_match('/\$this->success\(/', $content) || preg_match('/\$this->fail\(/', $content)) {
        $analysis['uses_success_fail'] = true;
    }
    
    // 检查是否直接使用response()->json()
    if (preg_match('/response\(\)->json\(/', $content)) {
        $analysis['uses_direct_json'] = true;
        
        // 提取使用位置
        preg_match_all('/response\(\)->json\([^;]+;/s', $content, $matches);
        $analysis['direct_json_locations'] = $matches[0] ?? [];
    }
    
    // 检查是否使用handleException
    if (preg_match('/\$this->handleException\(/', $content)) {
        $analysis['uses_handle_exception'] = true;
    }
    
    // 检查是否有try-catch块
    if (preg_match('/try\s*\{/', $content)) {
        $analysis['has_try_catch'] = true;
    }
    
    return $analysis;
}

// 查找所有控制器
$controllers = findControllers($baseDir);

echo "找到 " . count($controllers) . " 个控制器文件\n\n";
echo "=" . str_repeat("=", 80) . "\n";
echo "开始审查...\n";
echo "=" . str_repeat("=", 80) . "\n\n";

// 分析每个控制器
foreach ($controllers as $controller) {
    $analysis = analyzeController($controller);
    
    // 跳过BaseController本身
    if (str_contains($analysis['path'], 'BaseController.php')) {
        continue;
    }
    
    // 跳过Laravel默认的Controller基类
    if (str_contains($analysis['path'], 'Http/Controllers/Controller.php')) {
        continue;
    }
    
    // 检查继承问题
    if (!$analysis['extends_base']) {
        $results['inheritance_issues'][] = $analysis['path'];
    }
    
    // 检查直接使用response()->json()
    if ($analysis['uses_direct_json']) {
        $results['direct_json_usage'][] = [
            'path' => $analysis['path'],
            'locations' => $analysis['direct_json_locations'] ?? []
        ];
    }
    
    // 检查异常处理
    if ($analysis['has_try_catch'] && !$analysis['uses_handle_exception']) {
        $results['missing_exception_handling'][] = $analysis['path'];
    }
    
    // 检查是否完全符合规范
    if ($analysis['extends_base'] && 
        $analysis['uses_success_fail'] && 
        !$analysis['uses_direct_json']) {
        $results['compliant_controllers'][] = $analysis['path'];
    }
}

// 输出结果
echo "\n【审查结果】\n\n";

echo "1. 不继承BaseController的控制器 (" . count($results['inheritance_issues']) . "个):\n";
echo str_repeat("-", 80) . "\n";
if (empty($results['inheritance_issues'])) {
    echo "   ✓ 所有控制器都继承自BaseController\n";
} else {
    foreach ($results['inheritance_issues'] as $path) {
        echo "   ✗ " . $path . "\n";
    }
}
echo "\n";

echo "2. 直接使用response()->json()的控制器 (" . count($results['direct_json_usage']) . "个):\n";
echo str_repeat("-", 80) . "\n";
if (empty($results['direct_json_usage'])) {
    echo "   ✓ 所有控制器都使用success/fail方法\n";
} else {
    foreach ($results['direct_json_usage'] as $item) {
        echo "   ✗ " . $item['path'] . "\n";
        if (!empty($item['locations'])) {
            foreach ($item['locations'] as $location) {
                $preview = substr(trim($location), 0, 100);
                echo "      - " . $preview . (strlen($location) > 100 ? '...' : '') . "\n";
            }
        }
    }
}
echo "\n";

echo "3. 有try-catch但未使用handleException的控制器 (" . count($results['missing_exception_handling']) . "个):\n";
echo str_repeat("-", 80) . "\n";
if (empty($results['missing_exception_handling'])) {
    echo "   ✓ 所有异常处理都使用handleException方法\n";
} else {
    foreach ($results['missing_exception_handling'] as $path) {
        echo "   ✗ " . $path . "\n";
    }
}
echo "\n";

echo "4. 完全符合规范的控制器 (" . count($results['compliant_controllers']) . "个):\n";
echo str_repeat("-", 80) . "\n";
if (empty($results['compliant_controllers'])) {
    echo "   ✗ 没有完全符合规范的控制器\n";
} else {
    foreach ($results['compliant_controllers'] as $path) {
        echo "   ✓ " . $path . "\n";
    }
}
echo "\n";

// 统计摘要
echo "\n【统计摘要】\n";
echo str_repeat("=", 80) . "\n";
$totalControllers = count($controllers) - 2; // 减去BaseController和Controller
$issueCount = count($results['inheritance_issues']) + 
              count($results['direct_json_usage']) + 
              count($results['missing_exception_handling']);
$complianceRate = $totalControllers > 0 ? 
    round((count($results['compliant_controllers']) / $totalControllers) * 100, 2) : 0;

echo "总控制器数: " . $totalControllers . "\n";
echo "符合规范: " . count($results['compliant_controllers']) . " (" . $complianceRate . "%)\n";
echo "存在问题: " . $issueCount . "\n";
echo "  - 继承问题: " . count($results['inheritance_issues']) . "\n";
echo "  - 直接使用JSON: " . count($results['direct_json_usage']) . "\n";
echo "  - 异常处理问题: " . count($results['missing_exception_handling']) . "\n";
echo "\n";

// 保存详细报告到JSON文件
$reportPath = __DIR__ . '/controller_audit_report.json';
file_put_contents($reportPath, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "详细报告已保存到: " . $reportPath . "\n";
