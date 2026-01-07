<?php
/**
 * 第二轮修复英文残留
 * 
 * 使用方法：
 * docker exec fitness_php_v2 php scripts/fix_english_residue_round2.php
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "=" . str_repeat("=", 79) . "\n";
echo "第二轮修复英文残留\n";
echo "=" . str_repeat("=", 79) . "\n\n";

// 更多单词替换
$wordReplacements = [
    // 动词
    'Find' => '找到',
    'Anchor' => '固定',
    'Hinge' => '髋屈',
    'Grasp' => '握住',
    'Aim' => '目标',
    'Point' => '指向',
    'Break' => '弯曲',
    'Place' => '放置',
    'Begin' => '开始',
    'begin' => '开始',
    'Initiate' => '启动',
    'initiate' => '启动',
    'Hit' => '达到',
    'hit' => '达到',
    'Squeezing' => '挤压',
    'squeezing' => '挤压',
    'Turned' => '转向',
    'turned' => '转向',
    'facing' => '面向',
    'Facing' => '面向',
    
    // 名词
    'box' => '箱子',
    'Box' => '箱子',
    'corner' => '角落',
    'room' => '房间',
    'width' => '宽度',
    'height' => '高度',
    'depth' => '深度',
    'parallel' => '平行',
    'hamstring' => '腘绳肌',
    'temples' => '太阳穴',
    'fists' => '拳头',
    'loops' => '环',
    'grip' => '握法',
    'toes' => '脚趾',
    'weight' => '重量',
    'mobility' => '灵活性',
    'requirement' => '要求',
    'deficit' => '垫高',
    'Deficit' => '垫高',
    'goblet' => '高脚杯式',
    'point' => '点',
    'end' => '末端',
    
    // 形容词/副词
    'stable' => '稳定的',
    'bent' => '弯曲的',
    'relaxed' => '放松的',
    'double' => '双',
    'roughly' => '大约',
    'almost' => '几乎',
    'either' => '任一',
    'further' => '更远',
    'longer' => '更长',
    'outwards' => '向外',
    'front' => '前',
    'right' => '右',
    'left' => '左',
    'each' => '每个',
    'two' => '两个',
    
    // 介词/连词
    'by' => '在',
    'for' => '用于',
    'off' => '离开',
    'in' => '在',
    'would' => '会',
    'also' => '也',
    
    // 专有名词
    'Rex' => '霸王龙',
    'RDL' => '罗马尼亚硬拉',
    'Coan' => '科恩',
    'Silverback' => '银背',
    'Pallof' => '帕洛夫',
    'Bayesian' => '贝叶斯',
    'Bulgarian' => '保加利亚',
    'Romanian' => '罗马尼亚',
    'Sumo' => '相扑',
    'Jefferson' => '杰斐逊',
    'Zercher' => '泽奇',
    'Pendlay' => '彭德雷',
    'Yates' => '耶茨',
    'Arnold' => '阿诺德',
    'Scott' => '斯科特',
    'Preacher' => '牧师',
    'Hammer' => '锤式',
    'Spider' => '蜘蛛',
    'Skull' => '颅骨',
    'Crusher' => '粉碎者',
    'Kickback' => '后踢',
    'Pushdown' => '下压',
    'Pulldown' => '下拉',
    'Pullover' => '仰卧上拉',
    'Flye' => '飞鸟',
    'Fly' => '飞鸟',
    'Press' => '推举',
    'Row' => '划船',
    'Curl' => '弯举',
    'Extension' => '伸展',
    'Raise' => '上举',
    'Shrug' => '耸肩',
    'Crunch' => '卷腹',
    'Plank' => '平板支撑',
    'Lunge' => '弓步',
    'Squat' => '深蹲',
    'Deadlift' => '硬拉',
    
    // 器械
    'EZ' => 'EZ曲杆',
    'Smith' => '史密斯',
    'Trap' => '六角杆',
    'Hex' => '六角',
    'Landmine' => '地雷架',
    'landmine' => '地雷架',
    'Rack' => '架子',
    'Pin' => '插销',
    'Safety' => '安全',
    'Spotter' => '保护者',
    
    // 其他常见词
    'simultaneously' => '同时',
    'movement' => '动作',
    'range' => '范围',
    'motion' => '运动',
    'rep' => '次',
    'reps' => '次',
    'set' => '组',
    'sets' => '组',
    'tempo' => '节奏',
    'pause' => '暂停',
    'hold' => '保持',
    'squeeze' => '挤压',
    'contract' => '收缩',
    'stretch' => '拉伸',
    'lockout' => '锁定',
    'eccentric' => '离心',
    'concentric' => '向心',
    'isometric' => '等长',
    'neutral' => '中立',
    'supinated' => '反握',
    'pronated' => '正握',
    'overhand' => '正手',
    'underhand' => '反手',
    'mixed' => '混合',
    'hook' => '钩',
    'false' => '假',
    'thumbless' => '无拇指',
    'close' => '窄',
    'wide' => '宽',
    'narrow' => '窄',
    'medium' => '中等',
    'standard' => '标准',
    'modified' => '改良',
    'assisted' => '辅助',
    'weighted' => '负重',
    'bodyweight' => '自重',
    'unilateral' => '单侧',
    'bilateral' => '双侧',
    'alternating' => '交替',
    'single' => '单',
    'seated' => '坐姿',
    'standing' => '站姿',
    'lying' => '仰卧',
    'prone' => '俯卧',
    'supine' => '仰卧',
    'incline' => '上斜',
    'decline' => '下斜',
    'flat' => '平板',
    'overhead' => '过头',
    'behind' => '身后',
    'across' => '横跨',
    'reverse' => '反向',
    'inverted' => '倒置',
];

// 统计
$stats = [
    'files_scanned' => 0,
    'files_updated' => 0,
    'replacements' => 0,
];

// 遍历所有动作文件
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($exercisesDir)
);

foreach ($iterator as $file) {
    if ($file->getFilename() !== 'data.json') {
        continue;
    }
    
    $stats['files_scanned']++;
    $filePath = $file->getPathname();
    
    $content = file_get_contents($filePath);
    $data = json_decode($content, true);
    
    if (!$data) {
        continue;
    }
    
    $modified = false;
    $fieldsToCheck = ['name_zh', 'description_zh', 'correct_steps_zh', 'equipment_zh', 'primary_muscle_zh'];
    
    foreach ($fieldsToCheck as $field) {
        if (!isset($data[$field])) {
            continue;
        }
        
        if (is_array($data[$field])) {
            foreach ($data[$field] as $index => $value) {
                if (!is_string($value)) continue;
                
                $newValue = $value;
                foreach ($wordReplacements as $en => $zh) {
                    $pattern = '/\b' . preg_quote($en, '/') . '\b/';
                    if (preg_match($pattern, $newValue)) {
                        $newValue = preg_replace($pattern, $zh, $newValue);
                        $stats['replacements']++;
                    }
                }
                
                // 清理
                $newValue = preg_replace('/\s+/', ' ', $newValue);
                $newValue = trim($newValue);
                
                if ($newValue !== $value) {
                    $data[$field][$index] = $newValue;
                    $modified = true;
                }
            }
        } else {
            $newValue = $data[$field];
            foreach ($wordReplacements as $en => $zh) {
                $pattern = '/\b' . preg_quote($en, '/') . '\b/';
                if (preg_match($pattern, $newValue)) {
                    $newValue = preg_replace($pattern, $zh, $newValue);
                    $stats['replacements']++;
                }
            }
            
            $newValue = preg_replace('/\s+/', ' ', $newValue);
            $newValue = trim($newValue);
            
            if ($newValue !== $data[$field]) {
                $data[$field] = $newValue;
                $modified = true;
            }
        }
    }
    
    if ($modified) {
        file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $stats['files_updated']++;
    }
}

echo "扫描文件数: {$stats['files_scanned']}\n";
echo "更新文件数: {$stats['files_updated']}\n";
echo "替换次数: {$stats['replacements']}\n";
