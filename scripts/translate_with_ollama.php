<?php
/**
 * 使用 Ollama 翻译英文残留内容
 * 
 * 读取 translation_needed.json，使用 Ollama API 翻译，输出 translation_result.json
 * 
 * 使用方法：
 * docker exec fitness_php_v2 php scripts/translate_with_ollama.php
 */

// 配置
$ollamaHost = 'http://host.docker.internal:11434';
$model = 'qwen3:8b';
$inputFile = __DIR__ . '/../storage/app/translation_needed.json';
$outputFile = __DIR__ . '/../storage/app/translation_result.json';

echo "=" . str_repeat("=", 79) . "\n";
echo "使用 Ollama 翻译英文残留内容\n";
echo "=" . str_repeat("=", 79) . "\n\n";

// 检查输入文件
if (!file_exists($inputFile)) {
    echo "❌ 输入文件不存在: $inputFile\n";
    exit(1);
}

// 读取需要翻译的内容
$data = json_decode(file_get_contents($inputFile), true);
if (!$data) {
    echo "❌ 无法解析输入文件\n";
    exit(1);
}

echo "📖 读取到 " . count($data) . " 条需要翻译的记录\n\n";

// 测试 Ollama 连接
echo "🔗 测试 Ollama 连接...\n";
$ch = curl_init($ollamaHost . '/api/tags');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Ollama 连接失败 (HTTP $httpCode)\n";
    echo "请确保 Ollama 服务正在运行\n";
    exit(1);
}
echo "✅ Ollama 连接成功\n\n";

// 翻译函数
function translateText($text, $field, $ollamaHost, $model) {
    // 根据字段类型构建不同的提示词
    $fieldPrompts = [
        'name_zh' => '这是一个健身动作名称，请翻译成中文：',
        'equipment_zh' => '这是健身器械名称，请翻译成中文：',
        'primary_muscle_zh' => '这是肌肉名称，请翻译成中文：',
        'description_zh' => '这是健身动作描述，请翻译成流畅的中文：',
        'correct_steps_zh' => '这是健身动作步骤，请翻译成中文：',
    ];
    
    $prompt = $fieldPrompts[$field] ?? '请翻译成中文：';
    
    $fullPrompt = <<<EOT
{$prompt}

原文：{$text}

要求：
1. 只输出翻译结果，不要任何解释
2. 保持专业健身术语
3. 翻译准确、流畅
4. 不要添加任何额外内容

翻译结果：
EOT;

    $payload = json_encode([
        'model' => $model,
        'prompt' => $fullPrompt,
        'stream' => false,
        'options' => [
            'temperature' => 0.3,
            'num_predict' => 500,
        ]
    ]);
    
    $ch = curl_init($ollamaHost . '/api/generate');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return null;
    }
    
    $result = json_decode($response, true);
    if (!$result || !isset($result['response'])) {
        return null;
    }
    
    // 清理响应
    $translated = trim($result['response']);
    // 移除可能的引号
    $translated = trim($translated, '"\'');
    // 移除可能的"翻译结果："前缀
    $translated = preg_replace('/^翻译结果[：:]\s*/u', '', $translated);
    
    return $translated;
}

// 开始翻译
$results = [];
$stats = [
    'total' => count($data),
    'success' => 0,
    'failed' => 0,
];

$startTime = microtime(true);

foreach ($data as $index => $item) {
    $id = $item['id'];
    $field = $item['field'];
    $currentZh = $item['current_zh'];
    $englishSource = $item['english_source'];
    $stepIndex = $item['index'] ?? null;
    
    $progress = $index + 1;
    echo "\r[{$progress}/{$stats['total']}] ID={$id}, {$field}" . ($stepIndex !== null ? "[{$stepIndex}]" : "") . "...";
    
    // 翻译
    $translated = translateText($englishSource, $field, $ollamaHost, $model);
    
    if ($translated) {
        $results[] = [
            'id' => $id,
            'field' => $field,
            'index' => $stepIndex,
            'original_zh' => $currentZh,
            'english_source' => $englishSource,
            'translated_zh' => $translated,
        ];
        $stats['success']++;
        echo " ✅\n";
    } else {
        $results[] = [
            'id' => $id,
            'field' => $field,
            'index' => $stepIndex,
            'original_zh' => $currentZh,
            'english_source' => $englishSource,
            'translated_zh' => null,
            'error' => '翻译失败',
        ];
        $stats['failed']++;
        echo " ❌\n";
    }
    
    // 短暂延迟避免过载
    usleep(100000); // 0.1秒
}

$elapsedTime = microtime(true) - $startTime;

// 保存结果
file_put_contents($outputFile, json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "\n\n" . str_repeat("=", 80) . "\n";
echo "翻译完成\n";
echo str_repeat("=", 80) . "\n\n";

echo "总记录数: {$stats['total']}\n";
echo "成功翻译: {$stats['success']}\n";
echo "翻译失败: {$stats['failed']}\n";
echo "耗时: " . round($elapsedTime, 1) . " 秒 (" . round($elapsedTime / 60, 1) . " 分钟)\n";
echo "\n结果已保存到: $outputFile\n";

echo "\n下一步：运行 import_translations.php 将翻译结果写入源文件\n";
