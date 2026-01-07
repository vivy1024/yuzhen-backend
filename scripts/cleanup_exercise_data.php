<?php
/**
 * 动作数据源清理脚本
 * 
 * 功能：
 * 1. 删除冗余字段
 * 2. 简化嵌套对象结构
 * 3. 统一字段命名规范
 * 4. 从增强版数据集补充字段
 * 
 * 运行方式：
 * docker exec fitness_php_v2 php scripts/cleanup_exercise_data.php
 * 
 * @version 1.0.0
 * @date 2026-01-04
 */

// 配置
$sourceDir = __DIR__ . '/../storage/app/public/exercises_v2';
$enhancedDatasetPath = __DIR__ . '/../storage/app/enhanced_perfect_exercises_dataset.json';
$dryRun = in_array('--dry-run', $argv ?? []);

// 统计
$stats = [
    'total_files' => 0,
    'processed_files' => 0,
    'skipped_files' => 0,
    'errors' => [],
    'fields_removed' => [],
    'fields_simplified' => [],
    'fields_renamed' => [],
    'fields_added' => [],
];

echo "=== 动作数据源清理脚本 ===\n";
echo "模式: " . ($dryRun ? "预览模式 (不修改文件)" : "执行模式") . "\n\n";

// 加载增强版数据集
echo "加载增强版数据集...\n";
$enhancedData = loadEnhancedDataset($enhancedDatasetPath);
echo "已加载 " . count($enhancedData) . " 条增强数据\n\n";

// 遍历所有源文件
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getFilename() !== 'data.json') {
        continue;
    }
    
    $stats['total_files']++;
    $filePath = $file->getPathname();
    
    try {
        $result = processFile($filePath, $enhancedData, $dryRun, $stats);
        if ($result) {
            $stats['processed_files']++;
        } else {
            $stats['skipped_files']++;
        }
    } catch (Exception $e) {
        $stats['errors'][] = [
            'file' => $filePath,
            'error' => $e->getMessage()
        ];
    }
    
    // 进度显示
    if ($stats['total_files'] % 100 === 0) {
        echo "已处理: {$stats['total_files']} 个文件\n";
    }
}

// 输出统计
printStats($stats);

/**
 * 加载增强版数据集
 */
function loadEnhancedDataset(string $path): array {
    if (!file_exists($path)) {
        echo "警告: 增强版数据集不存在: $path\n";
        return [];
    }
    
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    
    if (!$data || !isset($data['enhanced_perfect_exercises'])) {
        echo "警告: 增强版数据集格式错误\n";
        return [];
    }
    
    // 按 ID 索引
    $indexed = [];
    foreach ($data['enhanced_perfect_exercises'] as $item) {
        if (isset($item['id'])) {
            $indexed[$item['id']] = $item;
        }
    }
    
    return $indexed;
}

/**
 * 处理单个文件
 */
function processFile(string $filePath, array $enhancedData, bool $dryRun, array &$stats): bool {
    $content = file_get_contents($filePath);
    $data = json_decode($content, true);
    
    if (!$data || !isset($data['id'])) {
        return false;
    }
    
    $originalData = $data;
    $exerciseId = $data['id'];
    
    // 1. 删除冗余字段
    $data = removeRedundantFields($data, $stats);
    
    // 2. 简化嵌套对象
    $data = simplifyNestedObjects($data, $stats);
    
    // 3. 统一字段命名
    $data = normalizeFieldNames($data, $stats);
    
    // 4. 从增强版数据集补充字段
    if (isset($enhancedData[$exerciseId])) {
        $data = addEnhancedFields($data, $enhancedData[$exerciseId], $stats);
    }
    
    // 检查是否有变化
    if ($data === $originalData) {
        return false;
    }
    
    // 写入文件
    if (!$dryRun) {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($filePath, $json);
    }
    
    return true;
}

/**
 * 删除冗余字段
 */
function removeRedundantFields(array $data, array &$stats): array {
    // 删除空字段
    $emptyFields = ['setup', 'setup_zh', 'performing', 'performing_zh', 'instructions'];
    foreach ($emptyFields as $field) {
        if (isset($data[$field]) && (empty($data[$field]) || $data[$field] === [])) {
            unset($data[$field]);
            $stats['fields_removed'][$field] = ($stats['fields_removed'][$field] ?? 0) + 1;
        }
    }
    
    // 清理 correct_steps 中的冗余字段
    if (isset($data['correct_steps']) && is_array($data['correct_steps'])) {
        foreach ($data['correct_steps'] as &$step) {
            if (is_array($step)) {
                // 删除 text_en_us (与 text 重复)
                if (isset($step['text_en_us'])) {
                    unset($step['text_en_us']);
                    $stats['fields_removed']['correct_steps.text_en_us'] = ($stats['fields_removed']['correct_steps.text_en_us'] ?? 0) + 1;
                }
                // 删除 exercise (冗余引用)
                if (isset($step['exercise'])) {
                    unset($step['exercise']);
                    $stats['fields_removed']['correct_steps.exercise'] = ($stats['fields_removed']['correct_steps.exercise'] ?? 0) + 1;
                }
            }
        }
    }
    
    return $data;
}

/**
 * 简化嵌套对象
 */
function simplifyNestedObjects(array $data, array &$stats): array {
    // difficulty 对象 → difficulty_zh 字符串
    if (isset($data['difficulty']) && is_array($data['difficulty'])) {
        $data['difficulty_zh'] = $data['difficulty']['name_zh'] ?? $data['difficulty']['name'] ?? '';
        $data['difficulty_en'] = $data['difficulty']['name'] ?? '';
        unset($data['difficulty']);
        $stats['fields_simplified']['difficulty'] = ($stats['fields_simplified']['difficulty'] ?? 0) + 1;
    }
    
    // force 对象 → force_zh 字符串
    if (isset($data['force']) && is_array($data['force'])) {
        $data['force_zh'] = $data['force']['name_zh'] ?? $data['force']['name'] ?? '';
        $data['force_en'] = $data['force']['name'] ?? '';
        unset($data['force']);
        $stats['fields_simplified']['force'] = ($stats['fields_simplified']['force'] ?? 0) + 1;
    }
    
    // mechanic 对象 → mechanic_zh 字符串
    if (isset($data['mechanic']) && is_array($data['mechanic'])) {
        $data['mechanic_zh'] = $data['mechanic']['name_zh'] ?? $data['mechanic']['name'] ?? '';
        $data['mechanic_en'] = $data['mechanic']['name'] ?? '';
        unset($data['mechanic']);
        $stats['fields_simplified']['mechanic'] = ($stats['fields_simplified']['mechanic'] ?? 0) + 1;
    }
    
    // grips 对象数组 → grips_zh 字符串数组
    if (isset($data['grips']) && is_array($data['grips'])) {
        $gripsZh = [];
        $gripsEn = [];
        foreach ($data['grips'] as $grip) {
            if (is_array($grip)) {
                $gripsZh[] = $grip['name_zh'] ?? $grip['name'] ?? '';
                $gripsEn[] = $grip['name'] ?? '';
            } else {
                $gripsZh[] = $grip;
                $gripsEn[] = $grip;
            }
        }
        $data['grips_zh'] = array_filter($gripsZh);
        $data['grips_en'] = array_filter($gripsEn);
        unset($data['grips']);
        $stats['fields_simplified']['grips'] = ($stats['fields_simplified']['grips'] ?? 0) + 1;
    }
    
    // correct_steps 对象数组 → correct_steps_en 字符串数组
    if (isset($data['correct_steps']) && is_array($data['correct_steps'])) {
        $stepsEn = [];
        foreach ($data['correct_steps'] as $step) {
            if (is_array($step) && isset($step['text'])) {
                $stepsEn[] = $step['text'];
            } elseif (is_string($step)) {
                $stepsEn[] = $step;
            }
        }
        $data['correct_steps_en'] = $stepsEn;
        unset($data['correct_steps']);
        $stats['fields_simplified']['correct_steps'] = ($stats['fields_simplified']['correct_steps'] ?? 0) + 1;
    }
    
    return $data;
}

/**
 * 统一字段命名
 */
function normalizeFieldNames(array $data, array &$stats): array {
    // name → name_en (如果是英文)
    if (isset($data['name']) && !isset($data['name_en'])) {
        $data['name_en'] = $data['name'];
        unset($data['name']);
        $stats['fields_renamed']['name→name_en'] = ($stats['fields_renamed']['name→name_en'] ?? 0) + 1;
    }
    
    // description → description_en (如果是英文)
    if (isset($data['description']) && !isset($data['description_en'])) {
        $data['description_en'] = $data['description'];
        unset($data['description']);
        $stats['fields_renamed']['description→description_en'] = ($stats['fields_renamed']['description→description_en'] ?? 0) + 1;
    }
    
    // primary_muscle → primary_muscle_en (如果没有 _zh 后缀且是英文)
    // 注意：当前 primary_muscle 已经是中文，保留为 primary_muscle_zh
    if (isset($data['primary_muscle']) && !isset($data['primary_muscle_en'])) {
        // 检查是否是中文
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $data['primary_muscle'])) {
            // 是中文，确保有 _zh 版本
            if (!isset($data['primary_muscle_zh'])) {
                $data['primary_muscle_zh'] = $data['primary_muscle'];
            }
        } else {
            // 是英文
            $data['primary_muscle_en'] = $data['primary_muscle'];
        }
        unset($data['primary_muscle']);
        $stats['fields_renamed']['primary_muscle'] = ($stats['fields_renamed']['primary_muscle'] ?? 0) + 1;
    }
    
    // equipment → equipment_zh (如果是中文)
    if (isset($data['equipment']) && !isset($data['equipment_en'])) {
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $data['equipment'])) {
            if (!isset($data['equipment_zh'])) {
                $data['equipment_zh'] = $data['equipment'];
            }
        } else {
            $data['equipment_en'] = $data['equipment'];
        }
        unset($data['equipment']);
        $stats['fields_renamed']['equipment'] = ($stats['fields_renamed']['equipment'] ?? 0) + 1;
    }
    
    return $data;
}

/**
 * 从增强版数据集补充字段
 */
function addEnhancedFields(array $data, array $enhanced, array &$stats): array {
    // 训练参数
    $trainingFields = ['rep_range', 'set_range', 'rest_period', 'intensity_percentage'];
    foreach ($trainingFields as $field) {
        if (isset($enhanced[$field]) && !isset($data[$field])) {
            $data[$field] = $enhanced[$field];
            $stats['fields_added'][$field] = ($stats['fields_added'][$field] ?? 0) + 1;
        }
    }
    
    // 安全信息
    $safetyFields = ['safety_level', 'safety_pre_check', 'equipment_risks'];
    foreach ($safetyFields as $field) {
        if (isset($enhanced[$field]) && !isset($data[$field])) {
            $data[$field] = $enhanced[$field];
            $stats['fields_added'][$field] = ($stats['fields_added'][$field] ?? 0) + 1;
        }
    }
    
    // 技术要点
    $techniqueFields = ['technique_checkpoints', 'kinetic_chain_type', 'rom_requirements'];
    foreach ($techniqueFields as $field) {
        if (isset($enhanced[$field]) && !isset($data[$field])) {
            $value = $enhanced[$field];
            // 处理 JSON 字符串
            if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
                $decoded = json_decode($value, true);
                if ($decoded !== null) {
                    $value = $decoded;
                }
            }
            $data[$field] = $value;
            $stats['fields_added'][$field] = ($stats['fields_added'][$field] ?? 0) + 1;
        }
    }
    
    // 营养建议
    $nutritionFields = ['key_nutrients', 'recommended_foods', 'nutrition_timing'];
    foreach ($nutritionFields as $field) {
        if (isset($enhanced[$field]) && !isset($data[$field])) {
            $data[$field] = $enhanced[$field];
            $stats['fields_added'][$field] = ($stats['fields_added'][$field] ?? 0) + 1;
        }
    }
    
    // 肌群命名规范化：使用增强版的 all_muscles_zh 替换源文件的 all_muscles
    // 增强版使用更规范的命名（如"肱二头肌"而非"二头肌"）
    if (isset($enhanced['all_muscles_zh']) && is_array($enhanced['all_muscles_zh'])) {
        $data['all_muscles_zh'] = $enhanced['all_muscles_zh'];
        // 删除旧的 all_muscles 字段
        if (isset($data['all_muscles'])) {
            unset($data['all_muscles']);
        }
        $stats['fields_added']['all_muscles_zh'] = ($stats['fields_added']['all_muscles_zh'] ?? 0) + 1;
    }
    
    // 主肌群命名规范化：使用增强版的 primary_muscle_zh
    if (isset($enhanced['primary_muscle_zh']) && !empty($enhanced['primary_muscle_zh'])) {
        $data['primary_muscle_zh'] = $enhanced['primary_muscle_zh'];
        $stats['fields_added']['primary_muscle_zh_normalized'] = ($stats['fields_added']['primary_muscle_zh_normalized'] ?? 0) + 1;
    }
    
    return $data;
}

/**
 * 输出统计信息
 */
function printStats(array $stats): void {
    echo "\n=== 处理完成 ===\n";
    echo "总文件数: {$stats['total_files']}\n";
    echo "已处理: {$stats['processed_files']}\n";
    echo "跳过: {$stats['skipped_files']}\n";
    echo "错误: " . count($stats['errors']) . "\n";
    
    if (!empty($stats['fields_removed'])) {
        echo "\n--- 删除的字段 ---\n";
        foreach ($stats['fields_removed'] as $field => $count) {
            echo "  $field: $count 次\n";
        }
    }
    
    if (!empty($stats['fields_simplified'])) {
        echo "\n--- 简化的字段 ---\n";
        foreach ($stats['fields_simplified'] as $field => $count) {
            echo "  $field: $count 次\n";
        }
    }
    
    if (!empty($stats['fields_renamed'])) {
        echo "\n--- 重命名的字段 ---\n";
        foreach ($stats['fields_renamed'] as $field => $count) {
            echo "  $field: $count 次\n";
        }
    }
    
    if (!empty($stats['fields_added'])) {
        echo "\n--- 补充的字段 ---\n";
        foreach ($stats['fields_added'] as $field => $count) {
            echo "  $field: $count 次\n";
        }
    }
    
    if (!empty($stats['errors'])) {
        echo "\n--- 错误详情 ---\n";
        foreach ($stats['errors'] as $error) {
            echo "  {$error['file']}: {$error['error']}\n";
        }
    }
}
