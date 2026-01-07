<?php

namespace App\Modules\Training\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Training Plan Completed Event
 * 
 * 训练计划完成事件
 */
class TrainingPlanCompleted
{
    use Dispatchable, SerializesModels;

    public array $plan;
    public string $completedAt;

    public function __construct(array $plan)
    {
        $this->plan = $plan;
        $this->completedAt = now()->toISOString();
    }
}

