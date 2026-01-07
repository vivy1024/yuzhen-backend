<?php

namespace App\Modules\Training\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Training Plan Created Event
 * 
 * 训练计划创建事件
 */
class TrainingPlanCreated
{
    use Dispatchable, SerializesModels;

    public array $plan;
    public string $createdAt;

    public function __construct(array $plan)
    {
        $this->plan = $plan;
        $this->createdAt = now()->toISOString();
    }
}

