<?php
/**
 * 导出简化版翻译文件
 * 
 * 生成一个简单的 JSON 文件，只包含需要翻译的英文原文
 * 方便使用网络翻译服务（如 DeepL、Google Translate）
 * 
 * 使用方法：
 * docker exec fitness_php_v2 php scripts/export_simple_translation.php
 */

$inputFile = __DIR__ . '/../storage/app/translation_needed.json';
$outputFile = __DIR__ . '/../storage/app/translation_simple.json';

echo "=" . str_repeat("=", 79) . "\n";
echo "导出简化版翻译文件\n";
echo "=" . str_repeat("=", 79) . "\n\n";

// 读取需要翻译的内容
$data = json_decode(file_get_contents($inputFile), true);
if (!$data) {
    echo "❌ 无法读取输入文件\n";
    exit(1);
}

echo "📖 读取到 " . count($data) . " 条记录\n\n";

// 生成简化版
$simple = [];
foreach ($data as $index => $item) {
    $key = $item['id'] . '_' . $item['field'];
    if ($item['index'] !== null) {
        $key .= '_' . $item['index'];
    }
    
    $simple[$key] = [
        'source' => $item['english_source'],
        'translation' => '', // 留空，等待填写
    ];
}

// 保存
file_put_contents($outputFile, json_encode($simple, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "✅ 已导出到: $outputFile\n";
echo "📊 共 " . count($simple) . " 条待翻译\n\n";

echo "使用说明：\n";
echo "1. 将 translation_simple.json 中的 source 字段内容复制到翻译服务\n";
echo "2. 将翻译结果填入对应的 translation 字段\n";
echo "3. 运行 import_simple_translation.php 导入翻译结果\n";
