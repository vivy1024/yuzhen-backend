<?php

namespace App\Modules\Exercise\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Exercise Resource
 *
 * 动作列表资源转换器
 *
 * @version 2.0.1
 * @date 2025-11-02
 * @changes 移除json_decode - Model已通过casts自动处理
 */
class ExerciseResource extends JsonResource
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
            'description' => $this->when(
                !$request->has('simple'),
                $this->description_zh ?? $this->description_en
            ),
            'primary_muscle' => $this->primary_muscle_en,
            'primary_muscle_zh' => $this->primary_muscle_zh,
            
            // ✅ 标准数组字段（三端统一）
            'muscles_primary' => $this->muscles_primary_zh ?? [$this->primary_muscle_zh],
            'muscles_secondary' => $this->muscles_secondary_zh ?? [],

            // 次要肌肉（兼容旧字段）
            'secondary_muscles' => $this->all_muscles_zh ?? [],

            'equipment' => $this->equipment_en,
            'equipment_zh' => $this->equipment_zh, // 修复：使用正确的字段名
            'difficulty' => $this->difficulty_en,
            'difficulty_zh' => $this->difficulty_zh ?? $this->getDifficultyZh(), // 优先使用数据库字段
            'force_type' => $this->force_en,
            'mechanic_type' => $this->mechanic_en,

            // 统计信息
            'rating' => $this->rating ?? 0,
            'view_count' => $this->view_count ?? 0,

            // ❌ 媒体资源禁用 - 版权问题（MuscleWiki）
            // 为未来乐刻健身房合作拍摄预留接口
            'image_urls' => $this->when(
                $this->relationLoaded('media'),
                fn() => $this->buildImageUrls()
            ),
            'video_urls' => $this->when(
                $this->relationLoaded('media'),
                fn() => $this->buildVideoUrls()
            ),
            'thumbnail_urls' => $this->when(
                $this->relationLoaded('media'),
                fn() => $this->buildThumbnailUrls()
            ),

            // 时间戳
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * 构建图片URL（按性别和角度分类）
     * ⚠️ 版权禁用：MuscleWiki媒体资源已禁用
     * 📅 禁用日期：2025-11-08
     * 🎯 计划：与乐刻健身房合作拍摄自有版权媒体
     */
    private function buildImageUrls(): array
    {
        // ❌ 版权问题：暂时禁用MuscleWiki媒体资源
        // 返回空数组，前端使用占位符
        return ['male' => [], 'female' => []];

        /* 原实现（已禁用）
        $images = $this->media->where('media_type', 'image');
        $result = ['male' => [], 'female' => []];

        foreach ($images as $media) {
            // 从路径解析性别和角度：exercises_v2/{id}/images/{gender}-{angle}.jpg
            $path = $media->cdn_url ?? $media->local_path ?? '';
            if (preg_match('/(male|female)-(\d+)/', $path, $matches)) {
                $gender = $matches[1];
                $angle = 'angle_' . $matches[2];
                $result[$gender][$angle] = $media->full_url;
            }
        }

        return $result;
        */
    }

    /**
     * 构建视频URL（按性别和角度分类）
     * ⚠️ 版权禁用：MuscleWiki媒体资源已禁用
     * 📅 禁用日期：2025-11-08
     * 🎯 计划：与乐刻健身房合作拍摄自有版权媒体
     */
    private function buildVideoUrls(): array
    {
        // ❌ 版权问题：暂时禁用MuscleWiki媒体资源
        // 返回空数组，前端使用占位符
        return ['male' => [], 'female' => []];

        /* 原实现（已禁用）
        $videos = $this->media->where('media_type', 'video');
        $result = ['male' => [], 'female' => []];

        foreach ($videos as $media) {
            // 从路径解析性别和角度：exercises_v2/{id}/videos/{gender}-{angle}.mp4
            $path = $media->cdn_url ?? $media->local_path ?? '';
            if (preg_match('/(male|female)-(\d+)/', $path, $matches)) {
                $gender = $matches[1];
                $angle = 'angle_' . $matches[2];
                $result[$gender][$angle] = $media->full_url;
            }
        }

        return $result;
        */
    }

    /**
     * 构建缩略图URL
     * ⚠️ 版权禁用：MuscleWiki媒体资源已禁用
     * 📅 禁用日期：2025-11-08
     * 🎯 计划：与乐刻健身房合作拍摄自有版权媒体
     */
    private function buildThumbnailUrls(): array
    {
        // ❌ 版权问题：暂时禁用MuscleWiki媒体资源
        // 返回空结构，前端使用占位符
        return [
            'primary' => null,
            'gallery' => [],
        ];

        /* 原实现（已禁用）
        $thumbnails = $this->media->where('media_type', 'thumbnail')
            ->sortBy('display_order');

        $result = [
            'primary' => $thumbnails->first()?->full_url,
            'gallery' => $thumbnails->pluck('full_url')->toArray(),
        ];

        return $result;
        */
    }

    /**
     * 获取难度中文名称
     * 将英文难度转换为中文
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
}
