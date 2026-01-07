<?php
/**
 * 第八轮修复英文残留 - 基于扫描报告的高频词汇
 * 包含瑜伽体式、动词、副词等常见残留
 */

$exercisesDir = __DIR__ . '/../storage/app/public/exercises_v2';

echo "第八轮修复英文残留 - 高频词汇批量替换\n\n";

$wordReplacements = [
    // ========== 瑜伽相关 ==========
    'breaths' => '呼吸',
    'breath' => '呼吸',
    'pose' => '体式',
    'Pose' => '体式',
    'several' => '几次',
    'switch' => '切换',
    'sides' => '侧',
    'side' => '侧',
    'tabletop' => '四足跪姿',
    'downward' => '下犬式',
    'dog' => '',
    'mountain' => '山式',
    'Tadasana' => '山式',
    'kneeling' => '跪姿',
    'Crescent' => '新月',
    'Moon' => '月亮',
    
    // ========== 动词（大写开头） ==========
    'Bring' => '将',
    'Drive' => '驱动',
    'Pick' => '抬起',
    'Reach' => '伸展',
    'Allow' => '让',
    'Grab' => '抓住',
    'Stop' => '停止',
    'Try' => '尝试',
    'Look' => '看向',
    'Tuck' => '收紧',
    'Continue' => '继续',
    'Once' => '一旦',
    'When' => '当',
    'Relax' => '放松',
    'Gently' => '轻轻地',
    
    // ========== 动词（小写） ==========
    'bring' => '将',
    'reach' => '到达',
    'reaches' => '到达',
    'touches' => '触碰',
    'touch' => '触碰',
    'making' => '确保',
    'pointing' => '指向',
    'placing' => '放置',
    'pushing' => '推',
    'pressing' => '按压',
    'feeling' => '感受',
    'releasing' => '释放',
    'switching' => '切换',
    'walking' => '行走',
    'straightening' => '伸直',
    'generating' => '产生',
    'maintaining' => '保持',
    'maintain' => '保持',
    'stabilize' => '稳定',
    'deepen' => '加深',
    'hinge' => '铰链',
    'pivot' => '旋转',
    'fold' => '折叠',
    'roll' => '滚动',
    'hang' => '悬垂',
    'dip' => '下沉',
    'grab' => '抓住',
    'help' => '帮助',
    'let' => '让',
    'try' => '尝试',
    'feel' => '感受',
    'come' => '回来',
    
    // ========== 名词 ==========
    'plate' => '杠铃片',
    'palms' => '手掌',
    'palm' => '手掌',
    'forehead' => '前额',
    'head' => '头部',
    'heart' => '心脏',
    'belly' => '腹部',
    'button' => '按钮',
    'ankle' => '脚踝',
    'buttocks' => '臀部',
    'shins' => '小腿',
    'ears' => '耳朵',
    'chin' => '下巴',
    'neck' => '颈部',
    'quad' => '股四头肌',
    'joints' => '关节',
    'mat' => '垫子',
    'straps' => '带子',
    'stirrups' => '脚蹬',
    'shape' => '形状',
    'manner' => '方式',
    'moment' => '片刻',
    'level' => '水平',
    'torque' => '扭矩',
    'prayer' => '祈祷',
    'banana' => '香蕉',
    
    // ========== 形容词/副词 ==========
    'briefly' => '短暂地',
    'nearly' => '几乎',
    'mostly' => '大部分',
    'deeply' => '深深地',
    'quickly' => '快速地',
    'heavy' => '沉重',
    'slight' => '轻微',
    'flared' => '外展',
    'flair' => '外展',
    'tucked' => '收紧',
    'stacked' => '叠放',
    'staggered' => '交错',
    'lifted' => '抬起',
    'planted' => '放置',
    'held' => '握住',
    'spread' => '展开',
    'comfortable' => '舒适',
    'long' => '长',
    'high' => '高',
    'much' => '更',
    'too' => '太',
    'easier' => '更容易',
    'closer' => '更近',
    'highest' => '最高',
    'outside' => '外侧',
    'next' => '旁边',
    'beneath' => '在下面',
    'inward' => '向内',
    'nearby' => '附近',
    
    // ========== 介词/连词 ==========
    'throughout' => '整个过程中',
    'before' => '之前',
    'onto' => '到',
    'going' => '去',
    'another' => '另一个',
    'will' => '将会',
    
    // ========== 其他 ==========
    'desired' => '目标',
    'number' => '数量',
    'variation' => '变式',
    'results' => '效果',
    'control' => '控制',
    'balance' => '平衡',
    'locking' => '锁定',
    'remains' => '保持',
    'drawing' => '收紧',
    
    // ========== 特殊名称 ==========
    'Rapunzel' => '长发公主',
    'Urdhva' => '上',
    'Dhanurasana' => '弓式',
    'Bosu' => '波速球',
    'BOSU' => '波速球',
    'Sissy' => '西西',
];

$stats = ['files_scanned' => 0, 'files_updated' => 0, 'replacements' => 0];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($exercisesDir));

foreach ($iterator as $file) {
    if ($file->getFilename() !== 'data.json') continue;
    
    $stats['files_scanned']++;
    $filePath = $file->getPathname();
    $data = json_decode(file_get_contents($filePath), true);
    if (!$data) continue;
    
    $modified = false;
    $fieldsToCheck = ['name_zh', 'description_zh', 'correct_steps_zh', 'equipment_zh', 'primary_muscle_zh'];
    
    foreach ($fieldsToCheck as $field) {
        if (!isset($data[$field])) continue;
        
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
                // 清理多余空格
                $newValue = preg_replace('/\s+/', ' ', trim($newValue));
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
            $newValue = preg_replace('/\s+/', ' ', trim($newValue));
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

echo "扫描文件: {$stats['files_scanned']}\n";
echo "更新文件: {$stats['files_updated']}\n";
echo "替换次数: {$stats['replacements']}\n";
