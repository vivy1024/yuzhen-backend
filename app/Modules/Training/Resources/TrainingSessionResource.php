<?php

namespace App\Modules\Training\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Training Session Resource v2.0
 * 
 * 训练会话资源转换器 - 统一字段命名
 * 完全对齐前端 TypeScript 类型定义
 * 
 * @version 2.0.0
 * @date 2025-11-01
 */
class TrainingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'user_id' => (string) $this->user_id,
            'plan_id' => $this->plan_id ? (string) $this->plan_id : null,
            'week' => $this->week,
            'day' => $this->day,
            'date' => $this->date?->format('Y-m-d'),
            'start_time' => $this->start_time?->toISOString(),
            'end_time' => $this->end_time?->toISOString(),
            'exercises' => $this->exercises ?? [],
            'total_volume' => $this->total_volume,
            'total_sets' => $this->total_sets,
            'avg_rpe' => $this->avg_rpe,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

