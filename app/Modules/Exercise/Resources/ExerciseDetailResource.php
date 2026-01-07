<?php

namespace App\Modules\Exercise\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Exercise Detail Resource
 *
 * 动作详情资源转换器
 *
 * @version 2.4.0
 * @date 2026-01-05
 * @changes 
 *   - 2.3.0: 添加新字段：variation_of, variations, joints, body_map_images
 *   - 2.4.0: body_map_images_local 转换为完整URL（使用本地文件系统）
 */
class ExerciseDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name_en,
            'name_zh' => $this->name_zh,
            'slug' => $this->slug,

            // 基础信息
            'description' => $this->description_en,
            'description_zh' => $this->description_zh,

            // 分类信息
            'primary_muscle' => $this->primary_muscle_en,
            'primary_muscle_zh' => $this->primary_muscle_zh,

            // 次要肌肉（Model已cast为array）
            'secondary_muscles' => $this->all_muscles_zh ?? [],

            'equipment' => $this->equipment_en,
            'equipment_zh' => $this->equipment_zh,
            'difficulty' => $this->difficulty_en,
            'difficulty_zh' => $this->difficulty_zh ?? $this->getDifficultyZh(),
            'force_type' => $this->force_en,
            'force_zh' => $this->force_zh,
            'mechanic_type' => $this->mechanic_en,
            'mechanic_zh' => $this->mechanic_zh,

            // JSON字段（Model已cast为array）
            'grips' => $this->grips_en ?? [],
            'grips_zh' => $this->grips_zh ?? [],
            'categories' => $this->categories ?? [],

            // 正确步骤（Model已cast为array）
            'correct_steps' => $this->correct_steps_en ?? [],
            'correct_steps_zh' => $this->correct_steps_zh ?? [],

            // 智能标签（Model已cast为array）
            'smart_tags' => $this->smart_tags ?? [],

            // ========== 安全相关字段 (v2.1.0新增) ==========
            // 安全等级: LOW_RISK/MODERATE_RISK/HIGH_RISK
            'safety_level' => $this->safety_level,
            // 训练前检查项 (v2.2.0新增)
            'safety_pre_check' => $this->safety_pre_check ?? [],
            // 器械风险 (v2.2.0新增)
            'equipment_risks' => $this->equipment_risks ?? [],
            // 安全警告信息
            'safety_warning_signs' => $this->safety_warning_signs,
            // 禁忌条件列表
            'contraindications' => $this->contraindications ?? [],
            // 是否为高风险动作
            'is_high_risk' => $this->safety_level === 'HIGH_RISK',

            // ========== 技术细节字段 ==========
            // 运动链类型: open_chain/closed_chain/mixed
            'kinetic_chain_type' => $this->kinetic_chain_type,
            // 技术检查点列表
            'technique_checkpoints' => $this->technique_checkpoints ?? [],
            // 关节活动度要求
            'rom_requirements' => $this->rom_requirements ?? [],

            // ========== 进阶相关字段 ==========
            // 进阶动作选项
            'progression_options' => $this->progression_options ?? [],
            // 退阶动作选项
            'regression_options' => $this->regression_options ?? [],

            // ========== 训练参数字段 ==========
            // 推荐次数范围
            'rep_range' => $this->rep_range,
            // 推荐组数范围
            'set_range' => $this->set_range,
            // 推荐休息时间
            'rest_period' => $this->rest_period,
            // 训练强度百分比 (v2.2.0新增)
            'intensity_percentage' => $this->intensity_percentage,

            // ========== 营养建议字段 (v2.2.0新增) ==========
            // 关键营养素
            'key_nutrients' => $this->key_nutrients ?? [],
            // 推荐食物
            'recommended_foods' => $this->recommended_foods ?? [],
            // 营养补充时机
            'nutrition_timing' => $this->nutrition_timing,

            // ========== 新增字段 (v2.3.0 2026-01-04) ==========
            // 变体来源（该动作是哪个动作的变体）
            'variation_of' => $this->variation_of,
            // 变体列表（该动作有哪些变体）
            'variations' => $this->variations ?? [],
            // 涉及关节
            'joints' => $this->joints ?? [],
            // 身体部位图（MuscleWiki提供 - 远程URL）- 禁用，使用本地文件
            'body_map_images' => [],
            // 身体部位图（本地路径 - 转换为完整URL）
            'body_map_images_local' => $this->getBodyMapImagesWithFullUrl(),
            // 所有涉及肌肉（从API更新）
            'all_muscles' => $this->all_muscles_zh ?? [],
            // 主要肌肉（从API更新）
            'muscles_primary' => $this->muscles_primary_zh ?? [],
            // 次要肌肉（从API更新）
            'muscles_secondary' => $this->muscles_secondary_zh ?? [],

            // ❌ 媒体资源禁用 - 版权问题（MuscleWiki）
            'media' => $this->whenLoaded('media', function () {
                return [];
            }),

            // 统计信息
            'rating' => $this->rating ?? 0,
            'view_count' => $this->view_count ?? 0,

            // 时间戳
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * 获取难度中文名称
     */
    private function getDifficultyZh(): string
    {
        $difficultyMap = [
            'Beginner' => '初学者',
            'Novice' => '零基础',
            'Intermediate' => '中级',
            'Advanced' => '高级',
        ];
        
        return $difficultyMap[$this->difficulty] ?? $this->difficulty ?? '中级';
    }

    /**
     * 获取带完整URL的bodymap图片
     * 
     * 将相对路径（如 bodymaps/male-front.png）转换为完整URL
     * 完整路径格式：/storage/exercises_v2/{range}/{subrange}/{id}/bodymaps/xxx.png
     * 
     * @return array
     */
    private function getBodyMapImagesWithFullUrl(): array
    {
        $localImages = $this->body_map_images_local ?? [];
        
        if (empty($localImages)) {
            return [];
        }

        // 计算动作的存储路径
        $id = $this->id;
        $range = floor(($id - 1) / 100) * 100;
        $rangeStr = sprintf('%04d-%04d', $range, $range + 99);
        $subRange = floor(($id - 1) / 10) * 10;
        $subRangeStr = sprintf('%04d-%04d', $subRange, $subRange + 9);
        
        // 基础路径
        $basePath = "exercises_v2/{$rangeStr}/{$subRangeStr}/{$id}";
        
        $result = [];
        
        foreach (['male', 'female'] as $gender) {
            if (!isset($localImages[$gender])) {
                continue;
            }
            
            $result[$gender] = [];
            
            foreach (['front', 'back'] as $view) {
                if (!empty($localImages[$gender][$view])) {
                    // 将相对路径转换为完整URL
                    // bodymaps/male-front.png -> /storage/exercises_v2/.../bodymaps/male-front.png
                    $relativePath = $localImages[$gender][$view];
                    $fullPath = "{$basePath}/{$relativePath}";
                    $result[$gender][$view] = asset("storage/{$fullPath}");
                }
            }
        }
        
        return $result;
    }
}
