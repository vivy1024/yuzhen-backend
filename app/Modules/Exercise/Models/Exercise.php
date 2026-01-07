<?php

namespace App\Modules\Exercise\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Exercise Model - 字段命名与源数据完全一致
 * 
 * @version 3.0.0
 * @date 2026-01-04
 */
class Exercise extends Model
{
    protected $table = 'exercises';

    protected $fillable = [
        // 基础信息
        'name_en',
        'name_zh',
        'slug',
        'description_en',
        'description_zh',
        // 肌肉信息
        'primary_muscle_en',
        'primary_muscle_zh',
        'all_muscles_zh',
        // 器械信息
        'equipment_en',
        'equipment_zh',
        // 难度信息
        'difficulty_en',
        'difficulty_zh',
        // 力量类型
        'force_en',
        'force_zh',
        // 动作类型
        'mechanic_en',
        'mechanic_zh',
        // 握法
        'grips_en',
        'grips_zh',
        // 正确步骤
        'correct_steps_en',
        'correct_steps_zh',
        // 智能标签
        'smart_tags',
        // 训练参数
        'rep_range',
        'set_range',
        'rest_period',
        'intensity_percentage',
        // 安全信息
        'safety_level',
        'safety_pre_check',
        'equipment_risks',
        // 技术细节
        'kinetic_chain_type',
        'technique_checkpoints',
        'rom_requirements',
        // 营养建议
        'key_nutrients',
        'recommended_foods',
        'nutrition_timing',
        // 进阶选项
        'progression_options',
        'regression_options',
        // 分类
        'categories',
        // 数据来源
        'data_source',
        'source_reference',
        'license_type',
        'original_source',
        'last_verified_at',
        'verified_by',
        // 统计
        'rating',
        'view_count',
        // 新增字段 (2026-01-04)
        'variation_of',
        'variations',
        'joints',
        'body_map_images',
        'body_map_images_local',
        // 肌肉详细信息
        'muscles_primary_en',
        'muscles_primary_zh',
        'muscles_secondary_en',
        'muscles_secondary_zh',
    ];

    protected $casts = [
        'all_muscles_zh' => 'array',
        'grips_en' => 'array',
        'grips_zh' => 'array',
        'correct_steps_en' => 'array',
        'correct_steps_zh' => 'array',
        'smart_tags' => 'array',
        'safety_pre_check' => 'array',
        'equipment_risks' => 'array',
        'technique_checkpoints' => 'array',
        'rom_requirements' => 'array',
        'key_nutrients' => 'array',
        'recommended_foods' => 'array',
        'progression_options' => 'array',
        'regression_options' => 'array',
        'categories' => 'array',
        'rating' => 'integer',
        'view_count' => 'integer',
        'last_verified_at' => 'datetime',
        // 新增字段 (2026-01-04)
        'variations' => 'array',
        'joints' => 'array',
        'body_map_images' => 'array',
        'body_map_images_local' => 'array',
        'muscles_primary_en' => 'array',
        'muscles_primary_zh' => 'array',
        'muscles_secondary_en' => 'array',
        'muscles_secondary_zh' => 'array',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(ExerciseMedia::class, 'exercise_id');
    }

    public function scopeByMuscle(Builder $query, string $muscle): Builder
    {
        return $query->where('primary_muscle_zh', 'like', "%{$muscle}%")
                     ->orWhere('primary_muscle_en', 'like', "%{$muscle}%");
    }

    public function scopeByEquipment(Builder $query, string $equipment): Builder
    {
        return $query->where('equipment_zh', 'like', "%{$equipment}%")
                     ->orWhere('equipment_en', 'like', "%{$equipment}%");
    }

    public function scopeByDifficulty(Builder $query, string $difficulty): Builder
    {
        return $query->where('difficulty_en', $difficulty)
                     ->orWhere('difficulty_zh', $difficulty);
    }

    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('name_en', 'like', "%{$keyword}%")
              ->orWhere('name_zh', 'like', "%{$keyword}%")
              ->orWhere('description_en', 'like', "%{$keyword}%")
              ->orWhere('description_zh', 'like', "%{$keyword}%");
        });
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->orderBy('view_count', 'desc');
    }

    public function incrementViewCount(): bool
    {
        return $this->increment('view_count');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name_zh ?? $this->name_en;
    }

    public function getDisplayDescriptionAttribute(): ?string
    {
        return $this->description_zh ?? $this->description_en;
    }
}
