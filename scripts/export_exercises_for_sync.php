<?php
/**
 * 导出Exercise数据为Neo4j导入格式
 * 从源文件目录读取所有data.json，合并为enhanced_perfect_exercises_dataset.json格式
 * 输出到 storage/app 目录，然后手动复制到目标位置
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';
$outputFile = __DIR__ . '/../storage/app/enhanced_perfect_exercises_dataset.json';

echo "导出Exercise数据为Neo4j导入格式\n\n";

$exercises = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($exercisesDir));

foreach ($iterator as $file) {
    if ($file->getFilename() !== 'data.json') continue;
    
    $data = json_decode(file_get_contents($file->getPathname()), true);
    if (!$data || !isset($data['id'])) continue;
    
    // 转换为Neo4j导入格式
    $exercise = [
        'id' => $data['id'],
        'name_zh' => $data['name_zh'] ?? '',
        'name_en' => $data['name_en'] ?? '',
        'description_zh' => $data['description_zh'] ?? '',
        'description_en' => $data['description_en'] ?? '',
        'primary_muscle_zh' => $data['primary_muscle_zh'] ?? '',
        'primary_muscle_en' => $data['primary_muscle_en'] ?? '',
        'all_muscles_zh_enhanced' => $data['all_muscles_zh'] ?? [],
        'equipment_zh' => $data['equipment_zh'] ?? [],
        'equipment_en' => $data['equipment_en'] ?? [],
        'difficulty' => $data['difficulty_zh'] ?? '中级',
        'force' => $data['force_zh'] ?? '',
        'mechanic' => $data['mechanic_zh'] ?? '',
        'grips' => $data['grips_zh'] ?? [],
        'correct_steps_zh' => $data['correct_steps_zh'] ?? [],
        'correct_steps_en' => $data['correct_steps_en'] ?? [],
        
        // 训练参数
        'training_parameters' => [
            'rep_range' => $data['rep_range'] ?? '',
            'set_range' => $data['set_range'] ?? '',
            'rest_period' => $data['rest_period'] ?? '',
            'intensity_percentage' => $data['intensity_percentage'] ?? '',
            'frequency' => $data['frequency'] ?? '',
            'progression' => $data['progression'] ?? '',
            'technique_focus' => $data['technique_focus'] ?? [],
        ],
        
        // 安全指南
        'safety_guidelines' => [
            'risk_level' => $data['safety_level'] ?? 'MEDIUM_RISK',
            'pre_workout_check' => $data['safety_pre_check'] ?? [],
            'during_workout' => $data['safety_during'] ?? [],
            'warning_signs' => $data['safety_warning_signs'] ?? [],
            'equipment_risks' => $data['equipment_risks'] ?? [],
        ],
        
        // 营养指导
        'nutrition_guidance' => [
            'key_nutrients' => $data['key_nutrients'] ?? [],
            'recommended_foods' => $data['recommended_foods'] ?? [],
            'timing_advice' => $data['nutrition_timing'] ?? '',
            'target_muscle' => $data['target_muscle_nutrition'] ?? '',
            'daily_requirements' => [
                'protein' => $data['daily_protein'] ?? '',
                'water' => $data['daily_water'] ?? '',
                'rest' => $data['daily_rest'] ?? '',
            ],
        ],
        
        // 元数据
        'enhancement_metadata' => [
            'source' => 'exercises_v2_source_files',
            'version' => '2.0.0',
            'updated_at' => date('Y-m-d H:i:s'),
        ],
    ];
    
    $exercises[] = $exercise;
}

// 按ID排序
usort($exercises, function($a, $b) {
    return $a['id'] - $b['id'];
});

// 输出
$output = [
    'enhanced_perfect_exercises' => $exercises,
    'metadata' => [
        'total_count' => count($exercises),
        'generated_at' => date('Y-m-d H:i:s'),
        'source' => 'yuzhen-backend/storage/app/public/exercises_v2',
    ],
];

$jsonContent = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
file_put_contents($outputFile, $jsonContent);

echo "导出完成: " . count($exercises) . " 个动作\n";
echo "输出文件: $outputFile\n";
