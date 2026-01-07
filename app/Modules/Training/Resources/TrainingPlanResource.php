<?php

namespace App\Modules\Training\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Training Plan Resource v2.0
 * 
 * 训练计划资源转换器 - 完整周期化结构
 * 完全对齐前端 TypeScript 类型定义
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class TrainingPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'user_id' => (string) $this->user_id,
            'name' => $this->name,
            'description' => $this->description ?? null,
            'type' => $this->type ?? 'manual',
            'source_template_id' => $this->source_template_id 
                ? (string) $this->source_template_id 
                : null,
            
            // 周期化结构
            'phases' => $this->phases ?? [],
            'weekly_schedule' => $this->weekly_schedule ?? [],
            
            // 统计信息
            'total_weeks' => $this->total_weeks ?? 0,
            'total_sessions' => $this->total_sessions ?? 0,
            'stats' => $this->stats ?? [
                'completed_sessions' => 0,
                'completion_rate' => 0,
                'current_phase_index' => 0,
                'current_week_index' => 0,
            ],
            
            // 状态
            'status' => $this->status ?? 'active',
            'is_active' => (bool) $this->is_active,
            
            // 时间戳
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

