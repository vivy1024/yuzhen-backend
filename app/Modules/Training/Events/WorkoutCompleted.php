<?php

namespace App\Modules\Training\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Workout Completed Event
 * 
 * 训练完成事件
 */
class WorkoutCompleted
{
    use Dispatchable, SerializesModels;

    public array $session;
    public string $completedAt;

    public function __construct(array $session)
    {
        $this->session = $session;
        $this->completedAt = now()->toISOString();
    }
}

